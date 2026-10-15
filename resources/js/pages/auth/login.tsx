import { Head, useForm, usePage } from '@inertiajs/react';
import { BarChart3, CreditCard, LoaderCircle, ScanText } from 'lucide-react';
import { type FormEventHandler } from 'react';

import { Logo } from '@/components/logo';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import logo from '../../../images/logo.png';
import type { SharedProps } from '@/types';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

const highlights = [
    { icon: BarChart3, text: 'Track installs, active users and growth in real time' },
    { icon: ScanText, text: 'See every translation, on the phone and on the server' },
    { icon: CreditCard, text: 'Follow trials and subscriptions on both stores' },
];

export default function Login() {
    const { routes } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(routes.login, {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <Head title="Log in" />

            <div className="flex flex-col items-center justify-center gap-6 bg-muted/40 p-6 md:p-10">
                <div className="flex w-full max-w-sm flex-col gap-6">
                    <div className="flex items-center gap-2 self-center font-medium">
                        <Logo className="size-8" />
                        Braille Recognition Admin
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Sign in to your account</CardTitle>
                            <CardDescription>Enter your email and password to access the dashboard.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="flex flex-col gap-6" onSubmit={submit}>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="email"
                                        autoFocus
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="admin@example.com"
                                    />
                                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="••••••••"
                                    />
                                    {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                                </div>

                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        className="size-4 rounded border-input"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                    />
                                    Remember me
                                </label>

                                <Button type="submit" className="w-full" disabled={processing}>
                                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                                    Log in
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="relative hidden overflow-hidden bg-zinc-950 lg:flex lg:flex-col lg:justify-between lg:p-12 lg:text-zinc-50">
                <div
                    className="absolute inset-0 opacity-40"
                    style={{
                        backgroundImage: 'radial-gradient(circle, rgba(255,255,255,0.14) 1px, transparent 1px)',
                        backgroundSize: '28px 28px',
                    }}
                />
                <div className="absolute top-1/3 left-1/2 size-[36rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-400/10 blur-3xl" />

                <div className="relative flex items-center gap-2 text-sm font-medium">
                    <img src={logo} alt="Braille Recognition" className="size-8 rounded-lg" />
                    Braille Recognition
                </div>

                <div className="relative flex flex-col gap-8">
                    <p className="max-w-md text-3xl leading-tight font-semibold text-balance">
                        Everything about the app, all in one place.
                    </p>
                    <ul className="flex flex-col gap-4">
                        {highlights.map(({ icon: Icon, text }) => (
                            <li key={text} className="flex items-center gap-3 text-sm text-zinc-300">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10">
                                    <Icon className="size-4" />
                                </span>
                                {text}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-xs text-zinc-500">&copy; {new Date().getFullYear()} Braille Recognition. All rights reserved.</p>
            </div>
        </div>
    );
}
