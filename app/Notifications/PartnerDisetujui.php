<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabar ke partner bahwa tokonya sudah dibuatkan.
 *
 * Formulir pendaftaran menjanjikan kabar lewat email, dan janji yang tidak
 * ditepati membuat orang menebak-nebak lalu mencoba masuk berulang kali.
 *
 * Sandi tidak pernah ikut dikirim — yang dipakai masuk adalah sandi yang
 * partner tentukan sendiri saat mendaftar, dan kami memang tidak menyimpannya
 * dalam bentuk yang bisa dibaca lagi.
 */
class PartnerDisetujui extends Notification
{
    use Queueable;

    public function __construct(private readonly Tenant $partner) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject('Toko '.$this->partner->name.' sudah aktif')
            ->greeting('Halo '.$this->partner->owner_name.',')
            ->line('Pendaftaran Anda disetujui. Toko '.$this->partner->name.' sudah bisa dibuka.')
            ->line('**Alamat toko:** '.$this->partner->alamat())
            ->action('Masuk ke Panel Admin', rtrim(config('app.url'), '/').'/admin/login')
            ->line('Masuk memakai email ini dan sandi yang Anda tentukan saat mendaftar.');

        if ($this->partner->expires_at !== null) {
            $pesan->line('Masa pakai sampai **'.$this->partner->expires_at->translatedFormat('d F Y').'**.');
        }

        return $pesan->line('Yang bisa Anda buka: '.$this->daftarHak().'.');
    }

    private function daftarHak(): string
    {
        return collect($this->partner->features ?? [])
            ->map(fn (string $f) => Tenant::FEATURES[$f] ?? $f)
            ->implode(', ');
    }
}
