import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, KeyRound, LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useRef } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import type { SharedProps } from '@/types';

type PasswordForm = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

export default function UpdatePassword() {
    const { routes } = usePage<SharedProps>().props;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm<PasswordForm>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(routes.settingsPasswordUpdate, {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (formErrors) => {
                if (formErrors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (formErrors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <DashboardLayout title="Change password" description="Update your account credentials">
            <Head title="Change password" />

            <Card className="max-w-lg">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <KeyRound className="size-4" />
                        Change password
                    </CardTitle>
                    <CardDescription>Use a long, unique password to keep your admin account secure.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="flex flex-col gap-6" onSubmit={submit}>
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">Current password</Label>
                            <Input
                                id="current_password"
                                ref={currentPasswordInput}
                                type="password"
                                autoComplete="current-password"
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                            />
                            {errors.current_password && <p className="text-sm text-destructive">{errors.current_password}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">New password</Label>
                            <Input
                                id="password"
                                ref={passwordInput}
                                type="password"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirm new password</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                            {errors.password_confirmation && <p className="text-sm text-destructive">{errors.password_confirmation}</p>}
                        </div>

                        {recentlySuccessful && (
                            <Alert>
                                <CheckCircle2 />
                                <AlertTitle>Password updated</AlertTitle>
                                <AlertDescription>Your password was changed successfully.</AlertDescription>
                            </Alert>
                        )}

                        <Button type="submit" className="w-fit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            Update password
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </DashboardLayout>
    );
}
