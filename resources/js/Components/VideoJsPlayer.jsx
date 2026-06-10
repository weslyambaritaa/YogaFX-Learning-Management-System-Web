import { useEffect, useRef } from 'react';
import videojs from 'video.js';
import 'video.js/dist/video-js.css';

export default function VideoJsPlayer({ src, poster = null, className = '' }) {
    const videoElementRef = useRef(null);
    const playerRef = useRef(null);

    useEffect(() => {
        if (!videoElementRef.current) {
            return undefined;
        }

        if (!playerRef.current) {
            playerRef.current = videojs(videoElementRef.current, {
                autoplay: false,
                controls: true,
                fluid: true,
                preload: 'auto',
                responsive: true,
                sources: src
                    ? [
                          {
                              src,
                              type: 'application/x-mpegURL',
                          },
                      ]
                    : [],
                poster: poster ?? undefined,
            });
        } else if (src) {
            playerRef.current.poster(poster ?? '');
            playerRef.current.src([
                {
                    src,
                    type: 'application/x-mpegURL',
                },
            ]);
        }

        return () => {
            playerRef.current?.dispose();
            playerRef.current = null;
        };
    }, [poster, src]);

    return (
        <div data-vjs-player className={className}>
            <video
                ref={videoElementRef}
                className="video-js vjs-big-play-centered overflow-hidden rounded-[24px]"
            />
        </div>
    );
}
