<?php

namespace App\Notifications;

use App\Models\Mapping;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CoverageNeedsReplacementNotification extends Notification
{
    use Queueable;

    protected $coverage;
    protected $outletCount;

    public function __construct(Mapping $coverage, int $outletCount)
    {
        $this->coverage    = $coverage;
        $this->outletCount = $outletCount;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('[CRM] Coverage Butuh Pengganti: ' . $this->coverage->id_coverage)
            ->greeting('Halo, ' . $notifiable->name)
            ->line('Coverage berikut telah dinonaktifkan dan membutuhkan pengganti:')
            ->line('**ID Coverage:** ' . $this->coverage->id_coverage)
            ->line('**Deskripsi:** ' . $this->coverage->cov_desc)
            ->line('**NIK Lama:** ' . $this->coverage->cov_nik)
            ->line('**Pilar:** ' . $this->coverage->cov_pilar)
            ->line('**Jumlah Outlet Terdampak:** ' . $this->outletCount . ' outlet')
            ->action('Kelola Mapping', url('/master/mapping'))
            ->line('Segera assign coverage baru untuk outlet yang terdampak.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type'          => 'coverage_needs_replacement',
            'id_coverage'   => $this->coverage->id_coverage,
            'cov_desc'      => $this->coverage->cov_desc,
            'cov_nik'       => $this->coverage->cov_nik,
            'cov_pilar'     => $this->coverage->cov_pilar,
            'outlet_count'  => $this->outletCount,
            'message'       => "Coverage {$this->coverage->id_coverage} ({$this->coverage->cov_desc}) dinonaktifkan. {$this->outletCount} outlet butuh pengganti.",
            'url'           => '/master/mapping',
        ];
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
}
