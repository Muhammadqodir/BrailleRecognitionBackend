import { Link } from '@inertiajs/react';

import { formatDate, formatDateTime, Pill, PlanBadge, RatingMark, type Plan } from '@/components/badges';
import { DetailField, DetailSection, DetailStat } from '@/components/data-table/detail-fields';
import { useEntityDetail } from '@/components/data-table/use-entity-detail';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { displayName, initials } from '@/lib/people';

interface UserTranslation {
    id: number;
    lang: string;
    text: string;
    image_url: string | null;
    engine: 'device' | 'server';
    rating: number | null;
    created_at: string;
}

interface UserDetail {
    id: number;
    name: string | null;
    email: string | null;
    provider: string | null;
    role: string | null;
    use_case: string | null;
    rc_user_id: string | null;
    legacy_id: number | null;
    platform: string | null;
    app_version: string | null;
    locale: string | null;
    events_count: number;
    last_seen_at: string | null;
    created_at: string | null;
    plan: Plan;
    subscription: {
        product_id: string | null;
        store: string | null;
        period_type: string | null;
        status: string;
        will_renew: boolean;
        environment: string;
        purchased_at: string | null;
        expires_at: string | null;
    } | null;
    translations_count: number;
    translations: UserTranslation[];
}

interface UserDetailDialogProps {
    userId: number | null;
    detailUrl: string | null;
    translationsUrl: string;
    onOpenChange: (open: boolean) => void;
}

export function UserDetailDialog({ userId, detailUrl, translationsUrl, onOpenChange }: UserDetailDialogProps) {
    const { data: user, loading } = useEntityDetail<UserDetail>(detailUrl);

    return (
        <Dialog open={userId !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] w-full max-w-2xl overflow-y-auto sm:max-w-2xl">
                {loading || !user ? (
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center gap-3">
                            <Skeleton className="size-12 rounded-full" />
                            <div className="flex flex-col gap-2">
                                <Skeleton className="h-4 w-40" />
                                <Skeleton className="h-3 w-24" />
                            </div>
                        </div>
                        <Skeleton className="h-32 w-full" />
                        <Skeleton className="h-32 w-full" />
                    </div>
                ) : (
                    <>
                        <DialogHeader>
                            <div className="flex items-center gap-3">
                                <Avatar className="size-12 shrink-0">
                                    <AvatarFallback>{initials(displayName(user))}</AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <DialogTitle className="truncate">{displayName(user)}</DialogTitle>
                                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span className="font-mono text-xs text-muted-foreground">#{user.id}</span>
                                        <PlanBadge plan={user.plan} />
                                        {user.provider && <Pill>{user.provider}</Pill>}
                                        {user.platform && <Pill>{user.platform}</Pill>}
                                    </div>
                                </div>
                            </div>
                        </DialogHeader>

                        <div className="grid grid-cols-3 gap-3">
                            <DetailStat label="Translations" value={user.translations_count.toLocaleString()} />
                            <DetailStat label="Events" value={user.events_count.toLocaleString()} />
                            <DetailStat label="Days since joining" value={user.created_at ? Math.floor((Date.now() - Date.parse(user.created_at)) / 86_400_000) : '—'} />
                        </div>

                        <Separator />

                        <DetailSection title="Account">
                            <DetailField label="Email" value={user.email} className="col-span-2" />
                            <DetailField label="Sign-in" value={user.provider} />
                            <DetailField label="Role" value={user.role} className="col-span-2" />
                            <DetailField label="Use case" value={user.use_case} />
                            <DetailField label="RevenueCat ID" value={user.rc_user_id && <span className="font-mono text-xs">{user.rc_user_id}</span>} className="col-span-2 sm:col-span-3" />
                            <DetailField label="Legacy ID" value={user.legacy_id} />
                        </DetailSection>

                        <Separator />

                        <DetailSection title="Subscription">
                            {user.subscription ? (
                                <>
                                    <DetailField label="Product" value={user.subscription.product_id} className="col-span-2" />
                                    <DetailField label="Store" value={user.subscription.store} />
                                    <DetailField label="Status" value={user.subscription.status} />
                                    <DetailField label="Period" value={user.subscription.period_type} />
                                    <DetailField label="Renews" value={user.subscription.will_renew ? 'Yes' : 'No'} />
                                    <DetailField label="Purchased" value={formatDate(user.subscription.purchased_at)} />
                                    <DetailField label="Expires" value={formatDate(user.subscription.expires_at)} />
                                    <DetailField label="Environment" value={user.subscription.environment} />
                                </>
                            ) : (
                                <DetailField label="Status" value="Never subscribed" />
                            )}
                        </DetailSection>

                        <Separator />

                        <DetailSection title="Activity">
                            <DetailField label="Last seen" value={formatDateTime(user.last_seen_at)} />
                            <DetailField label="Joined" value={formatDate(user.created_at)} />
                            <DetailField label="App version" value={user.app_version} />
                            <DetailField label="Device language" value={user.locale} />
                        </DetailSection>

                        {user.translations.length > 0 && (
                            <>
                                <Separator />
                                <div className="flex flex-col gap-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Recent translations ({user.translations.length} of {user.translations_count})
                                        </h3>
                                        <Link href={`${translationsUrl}?user_id=${user.id}`} className="text-xs hover:underline">
                                            View all
                                        </Link>
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        {user.translations.map((translation) => (
                                            <div key={translation.id} className="flex items-center gap-3 rounded-lg border border-border bg-muted/30 px-3 py-2">
                                                <ScanThumb url={translation.image_url} />
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-medium">
                                                        {translation.text || <span className="text-muted-foreground">No text found</span>}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {translation.lang} · {translation.engine === 'device' ? 'on the phone' : 'OCR server'} ·{' '}
                                                        {formatDateTime(translation.created_at)}
                                                    </div>
                                                </div>
                                                <RatingMark rating={translation.rating} />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </>
                        )}
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

/** A small square of the marked-up scan; a plain tile when there is none or it fails to load. */
export function ScanThumb({ url, className = 'size-10' }: { url: string | null; className?: string }) {
    return (
        <div className={`${className} shrink-0 overflow-hidden rounded-md border border-border bg-muted`}>
            {url && (
                <img
                    src={url}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                    onError={(event) => {
                        event.currentTarget.style.display = 'none';
                    }}
                />
            )}
        </div>
    );
}
