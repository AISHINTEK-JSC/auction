<?php

namespace App\Models;

use App\Enums\Gender;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $google_id
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property string|null $address
 * @property Gender|null $gender
 * @property Carbon|null $birthday
 * @property-read ShippingAddress|null $shippingAddress
 * @property string|null $avatar_path
 * @property UserRole $role
 * @property string|null $member_code
 * @property Carbon|null $blocked_at
 * @property string|null $block_reason
 * @property Carbon|null $bid_banned_until
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
#[Fillable(['name', 'email', 'password', 'phone', 'address', 'gender', 'birthday', 'avatar_path'])]
#[Hidden(['password', 'google_id', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'gender' => Gender::class,
            // Sent to the page as a plain date, the format of a date input.
            'birthday' => 'date:Y-m-d',
            'blocked_at' => 'datetime',
            'bid_banned_until' => 'datetime',
        ];
    }

    /**
     * Where the member wants purchases delivered, if they have said.
     *
     * @return HasOne<ShippingAddress, $this>
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(ShippingAddress::class);
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

    /**
     * The member proved the number on their profile is theirs, by typing back
     * the code texted to it. Needed to bid and to post listings.
     */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    /**
     * Banned from bidding for a while, after giving up auction results too
     * often (see App\Services\ForfeitPenalty).
     */
    public function isBidBanned(): bool
    {
        return $this->bid_banned_until !== null && $this->bid_banned_until->isFuture();
    }

    /**
     * @return HasMany<SavedSearch, $this>
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * @return HasMany<Forfeit, $this>
     */
    public function forfeits(): HasMany
    {
        return $this->hasMany(Forfeit::class);
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

        // A new number has to be confirmed again, whoever changed it (the
        // member on their profile or an admin in the panel).
        static::updating(function (self $user): void {
            if ($user->isDirty('phone') && ! $user->isDirty('phone_verified_at')) {
                $user->phone_verified_at = null;
            }
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
