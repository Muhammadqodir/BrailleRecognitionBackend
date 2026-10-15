/** How an app account is named in the dashboard: most never give a name, and anonymous ones have no email either. */
export function displayName(user: { id: number; name: string | null; email?: string | null; provider?: string | null }): string {
    if (user.name) {
        return user.name;
    }

    if (user.email) {
        return user.email.split('@')[0];
    }

    return user.provider === 'anonymous' ? `Anonymous #${user.id}` : `User #${user.id}`;
}

export function initials(name: string): string {
    return (
        name
            .replace(/#\d+/, '')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase())
            .join('') || '?'
    );
}
