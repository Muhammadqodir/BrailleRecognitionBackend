import logo from '../../images/logo.png';
import { cn } from '@/lib/utils';

/** The app icon: an opaque tile with its own background, so it reads the same in both themes. */
export function Logo({ className }: { className?: string }) {
    return <img src={logo} alt="Braille Recognition" className={cn('rounded-lg object-cover', className)} />;
}
