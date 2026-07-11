import { SVGAttributes } from 'react';

export default function ResumoIcon({
    size = 36,
    ...props
}: SVGAttributes<SVGElement> & { size?: number }) {
    return (
        <svg
            {...props}
            width={size}
            height={size}
            viewBox="0 0 64 64"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect width="64" height="64" rx="14" fill="#0d3d3b" />
            <path
                d="M17 14h24c1.1 0 2 .9 2 2v8h8c1.1 0 2 .9 2 2v24c0 1.1-.9 2-2 2H17c-1.1 0-2-.9-2-2V16c0-1.1.9-2 2-2z"
                fill="#ffffff"
            />
            <path d="M41 14v8h8L41 14z" fill="#d5ebe7" />
            <path
                d="M23 28h18M23 34h18M23 40h12"
                stroke="#0d3d3b"
                strokeWidth="3.5"
                strokeLinecap="round"
            />
            <circle cx="45" cy="46" r="9" fill="#e07a5f" />
            <path
                d="M41.2 46.2 44 49l5.2-5.8"
                fill="none"
                stroke="#ffffff"
                strokeWidth="2.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
