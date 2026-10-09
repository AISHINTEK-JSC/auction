<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $avatar_path
 * @property UserRole $role
 * @property string|null $member_code
 * @property Carbon|null $blocked_at
 * @property string|null $block_reason
 * @property-read string|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'address', 'avatar_path'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[Appends(['avatar'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null);
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Admins and moderators: the people allowed into the admin panel.
     */
    public function isStaff(): bool
    {
        return $this->role === UserRole::Admin || $this->role === UserRole::Moderator;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && ! $this->isBlocked();
    }

    /**
     * Every member gets a number once, at sign-up, and keeps it for good. It is
     * what they take part in giveaways with.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->member_code ??= self::newMemberCode();
        });
    }

    public static function newMemberCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        } while (self::query()->where('member_code', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsToMany<Listing, $this>
     */
    public function watchedListings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_watchers');
    }

    /**
     * Listings this member has bid on.
     *
     * @return BelongsToMany<Listing, $this>
     */
    public function bidListings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_bidders')->withPivot('last_bid_price')->withTimestamps();
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }
}
