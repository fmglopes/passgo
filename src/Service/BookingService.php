<?php
namespace PassGo\Service;

use PassGo\Repository\AppointmentRepository;
use PassGo\Repository\ServiceRepository;
use PassGo\Repository\SlotRepository;
use PDO;

/** Business rules for booking and cancelling appointments (queue tickets). */
class BookingService
{
    private $pdo;
    private $services;
    private $slots;
    private $appointments;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->services = new ServiceRepository($pdo);
        $this->slots = new SlotRepository($pdo);
        $this->appointments = new AppointmentRepository($pdo);
    }

    /** Ticket code like PG-7K3M9Q. Ambiguous characters (0, O, 1, I) are left out. */
    public static function generateTicketCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'PG-' . $code;
    }

    /**
     * Book a slot for a service. Returns the new appointment id.
     * Runs in one transaction and locks the slot row, so a slot can never be overbooked.
     *
     * @throws BookingException when a rule is not satisfied
     */
    public function book(int $userId, int $serviceId, int $slotId): int
    {
        $this->pdo->beginTransaction();
        try {
            $service = $this->services->find($serviceId);
            if (!$service) {
                throw new BookingException('We could not find that service.');
            }
            if (!$service['requires_appointment']) {
                throw new BookingException('This service does not need an appointment. You can simply walk in.');
            }

            $slot = $this->slots->lock($slotId);
            if (!$slot || (int) $slot['institution_id'] !== (int) $service['institution_id']) {
                throw new BookingException('That time slot is not available for this service.');
            }
            if ($slot['is_past']) {
                throw new BookingException('That time has already passed. Please choose another slot.');
            }
            if ($this->appointments->countBookedInSlot($slotId) >= (int) $slot['capacity']) {
                throw new BookingException('Sorry, that slot has just become full. Please choose another one.');
            }
            if ($this->appointments->hasActiveInSlot($userId, $slotId)) {
                throw new BookingException('You already have an appointment in this time slot.');
            }
            if ($this->appointments->hasActiveForService($userId, $serviceId)) {
                throw new BookingException('You already have an active appointment for this service. Cancel it first if you want to change the time.');
            }

            $code = $this->uniqueTicketCode();
            $id = $this->appointments->create($userId, $serviceId, $slotId, $code);
            $this->pdo->commit();
            return $id;
        } catch (BookingException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() === '23505') { // unique violation: the database safety net caught a duplicate
                throw new BookingException('You already have an appointment for this service or time.');
            }
            throw $e;
        }
    }

    /**
     * Cancel an upcoming appointment that belongs to the user.
     *
     * @throws BookingException when it cannot be cancelled
     */
    public function cancel(int $userId, int $appointmentId): void
    {
        $this->pdo->beginTransaction();
        try {
            $appt = $this->appointments->lockForUser($appointmentId, $userId);
            if (!$appt) {
                throw new BookingException('We could not find that appointment.');
            }
            if ($appt['status'] !== 'booked') {
                throw new BookingException('This appointment is already ' . $appt['status'] . '.');
            }
            if (!$appt['is_upcoming']) {
                throw new BookingException('This appointment is in the past and can no longer be cancelled.');
            }
            $this->appointments->markCancelled($appointmentId);
            $this->pdo->commit();
        } catch (BookingException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function uniqueTicketCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = self::generateTicketCode();
            if (!$this->appointments->codeExists($code)) {
                return $code;
            }
        }
        throw new \RuntimeException('Could not generate a unique ticket code.');
    }
}
