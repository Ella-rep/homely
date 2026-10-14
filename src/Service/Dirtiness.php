<?php

namespace App\Service;

use App\Entity\RoomTask;
use DateTimeImmutable;

/**
 * Toute la logique de "saleté" / échéance vit ici, côté backend uniquement.
 * Le frontend ne fait qu'afficher status / progressPercent / dueLabel tels que renvoyés par l'API.
 */
final class Dirtiness
{
    public const STATUS_CLEAN = 'clean';
    public const STATUS_DUE_SOON = 'due_soon';
    public const STATUS_OVERDUE = 'overdue';

    private const DUE_SOON_THRESHOLD = 0.7;

    public static function referenceDate(RoomTask $task): DateTimeImmutable
    {
        return $task->getLastDoneAt() ?? $task->getCreatedAt();
    }

    public static function daysSinceReference(RoomTask $task, ?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();
        $diff = self::referenceDate($task)->diff($now);

        return (int) $diff->days;
    }

    public static function ratio(RoomTask $task, ?DateTimeImmutable $now = null): float
    {
        $frequency = max(1, $task->getFrequencyDays());

        return self::daysSinceReference($task, $now) / $frequency;
    }

    public static function status(RoomTask $task, ?DateTimeImmutable $now = null): string
    {
        $ratio = self::ratio($task, $now);

        if ($ratio >= 1.0) {
            return self::STATUS_OVERDUE;
        }

        if ($ratio >= self::DUE_SOON_THRESHOLD) {
            return self::STATUS_DUE_SOON;
        }

        return self::STATUS_CLEAN;
    }

    public static function progressPercent(RoomTask $task, ?DateTimeImmutable $now = null): int
    {
        return (int) min(100, round(self::ratio($task, $now) * 100));
    }

    public static function dueLabel(RoomTask $task, ?DateTimeImmutable $now = null): string
    {
        $remaining = $task->getFrequencyDays() - self::daysSinceReference($task, $now);

        if ($remaining > 1) {
            return sprintf('Dans %d jours', $remaining);
        }

        if ($remaining === 1) {
            return 'Demain';
        }

        if ($remaining === 0) {
            return "Aujourd'hui";
        }

        $late = abs($remaining);

        return $late === 1 ? '1 jour de retard' : sprintf('%d jours de retard', $late);
    }

    /**
     * @param RoomTask[] $tasks
     */
    public static function aggregateStatus(array $tasks, ?DateTimeImmutable $now = null): string
    {
        $hasOverdue = false;
        $hasDueSoon = false;

        foreach ($tasks as $task) {
            $status = self::status($task, $now);
            if ($status === self::STATUS_OVERDUE) {
                $hasOverdue = true;
            } elseif ($status === self::STATUS_DUE_SOON) {
                $hasDueSoon = true;
            }
        }

        if ($hasOverdue) {
            return self::STATUS_OVERDUE;
        }

        if ($hasDueSoon) {
            return self::STATUS_DUE_SOON;
        }

        return self::STATUS_CLEAN;
    }

    /**
     * @param RoomTask[] $tasks
     */
    public static function countByStatus(array $tasks, string $status, ?DateTimeImmutable $now = null): int
    {
        return count(array_filter(
            $tasks,
            static fn (RoomTask $task) => self::status($task, $now) === $status
        ));
    }
}
