import { useEffect, useRef, useState } from 'react';

const DEFAULT_THRESHOLD = 8;
const DEFAULT_TOP_OFFSET = 8;

export default function useScrollDirection({
    threshold = DEFAULT_THRESHOLD,
    topOffset = DEFAULT_TOP_OFFSET,
} = {}) {
    const [direction, setDirection] = useState('up');
    const [isAtTop, setIsAtTop] = useState(true);
    const lastScrollYRef = useRef(0);

    useEffect(() => {
        lastScrollYRef.current = window.scrollY;

        const handleScroll = () => {
            const currentScrollY = window.scrollY;

            setIsAtTop(currentScrollY <= topOffset);

            const delta = currentScrollY - lastScrollYRef.current;

            if (Math.abs(delta) < threshold) {
                return;
            }

            setDirection(delta > 0 ? 'down' : 'up');
            lastScrollYRef.current = currentScrollY;
        };

        window.addEventListener('scroll', handleScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', handleScroll);
        };
    }, [threshold, topOffset]);

    return { direction, isAtTop };
}
