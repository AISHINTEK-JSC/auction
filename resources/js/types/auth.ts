export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    phone: string | null;
    /** Set once the number was confirmed by an SMS code; needed to bid and to post. */
    phone_verified_at: string | null;
    address: string | null;
    gender: 'male' | 'female' | 'other' | null;
    /** YYYY-MM-DD. */
    birthday: string | null;
    role: 'user' | 'moderator' | 'admin';
    /** Permanent member number, used to join giveaways. */
    member_code: string | null;
    /** Set while the member may not bid, after giving up auction results too often. */
    bid_banned_until: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
