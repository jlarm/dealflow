import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * The DealFlow mark. The navy "D" follows the current text color so it can
 * switch to white in dark mode; the teal flow lines keep the brand color.
 */
export default function AppLogoIcon({
    className,
    ...props
}: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="-24 52 1735 1107"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('text-[#051D43] dark:text-white', className)}
        >
            <path
                fill="currentColor"
                d="M260 76 H1048 C1416 76 1687 319 1687 640 C1687 936 1451 1135 1160 1135 H838 Q818 1135 830 1117 L979 899 Q987 887 1002 887 H1083 C1254 887 1376 782 1376 627 C1376 470 1251 349 1070 349 H246 Q227 349 227 326 V112 Q227 91 246 80 Q252 76 260 76 Z"
            />
            <path
                fill="#008F95"
                d="M0 1135 C312 759 662 520 1053 486 C1171 475 1240 477 1304 502 Q1325 511 1303 519 C898 526 550 735 274 1135 Z"
            />
            <path
                fill="#008F95"
                d="M381 1135 Q365 1135 375 1118 C625 769 913 569 1308 536 Q1335 533 1318 551 C1030 652 866 879 712 1126 Q708 1135 696 1135 Z"
            />
        </svg>
    );
}
