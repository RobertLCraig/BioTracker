<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * User model with TOTP MFA support, Sanctum API tokens, and media attachments.
 */
class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, InteractsWithMedia, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'totp_secret',
        'totp_enabled',
        'totp_confirmed_at',
        'totp_recovery_codes',
        'privacy_consented_at',
        'terms_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'totp_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'totp_enabled' => 'boolean',
            'totp_confirmed_at' => 'datetime',
            'totp_recovery_codes' => 'array',
            'privacy_consented_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /**
     * Enable TOTP for this user with the given secret.
     */
    public function enableTotp(string $secret): void
    {
        $this->update([
            'totp_secret' => Crypt::encryptString($secret),
            'totp_enabled' => true,
            'totp_confirmed_at' => now(),
            'totp_recovery_codes' => $this->generateRecoveryCodes(),
        ]);
    }

    /**
     * Verify a TOTP code against the user's stored secret.
     */
    public function verifyTotp(string $code): bool
    {
        if (! $this->totp_secret) {
            return false;
        }

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($this->totp_secret);

        return $google2fa->verifyKey($secret, $code);
    }

    /**
     * Generate a set of hashed recovery codes.
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        $count = config('biotracker.security.mfa_recovery_codes_count', 8);

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Hash::make(Str::random(10) . '-' . Str::random(10));
        }

        return $codes;
    }

    /**
     * Generate plain-text recovery codes and store their hashes.
     *
     * @return array<int, string> Plain-text codes for display to the user
     */
    public function generateAndStoreRecoveryCodes(): array
    {
        $plainCodes = [];
        $hashedCodes = [];
        $count = config('biotracker.security.mfa_recovery_codes_count', 8);

        for ($i = 0; $i < $count; $i++) {
            $plain = Str::random(10) . '-' . Str::random(10);
            $plainCodes[] = $plain;
            $hashedCodes[] = Hash::make($plain);
        }

        $this->update(['totp_recovery_codes' => $hashedCodes]);

        return $plainCodes;
    }

    /**
     * Attempt to use a recovery code. Returns true if the code was valid and consumed.
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->totp_recovery_codes ?? [];

        foreach ($codes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($codes[$index]);
                $this->update(['totp_recovery_codes' => array_values($codes)]);
                return true;
            }
        }

        return false;
    }

    /**
     * Disable TOTP for this user.
     */
    public function disableTotp(): void
    {
        $this->update([
            'totp_secret' => null,
            'totp_enabled' => false,
            'totp_confirmed_at' => null,
            'totp_recovery_codes' => null,
        ]);
    }

    /**
     * Get the decrypted TOTP secret.
     */
    public function getDecryptedTotpSecret(): ?string
    {
        return $this->totp_secret ? Crypt::decryptString($this->totp_secret) : null;
    }
}
