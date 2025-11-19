import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="18" fill="currentColor" />
            <path
                d="M12 15C12 13.8954 12.8954 13 14 13H26C27.1046 13 28 13.8954 28 15V25C28 26.1046 27.1046 27 26 27H14C12.8954 27 12 26.1046 12 25V15Z"
                fill="white"
            />
            <circle cx="16" cy="19" r="2" fill="currentColor" />
            <circle cx="24" cy="19" r="2" fill="currentColor" />
            <path
                d="M14 23C14 22.4477 14.4477 22 15 22H25C25.5523 22 26 22.4477 26 23C26 23.5523 25.5523 24 25 24H15C14.4477 24 14 23.5523 14 23Z"
                fill="currentColor"
            />
        </svg>
    );
}
