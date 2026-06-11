import { useEffect, useRef, useState } from 'react';
import 'video.js/dist/video-js.css';

const HLS_SOURCE_TYPE = 'application/vnd.apple.mpegurl';

export default function VideoJsPlayer({
    src,
    poster = null,
    className = '',
    onPlaybackError = null,
    onProgressUpdate = null,
}) {
    const containerRef = useRef(null);
    const playerRef = useRef(null);
    const latestSourceRef = useRef(src);
    const latestPosterRef = useRef(poster);
    const latestPlaybackErrorHandlerRef = useRef(onPlaybackError);
    const latestProgressHandlerRef = useRef(onProgressUpdate);
    const lastReportedProgressRef = useRef(0);
    const [loadFailed, setLoadFailed] = useState(false);

    useEffect(() => {
        latestSourceRef.current = src;
        latestPosterRef.current = poster;
        latestPlaybackErrorHandlerRef.current = onPlaybackError;
        latestProgressHandlerRef.current = onProgressUpdate;

        if (!playerRef.current) {
            return;
        }

        playerRef.current.poster(poster ?? '');

        if (src) {
            playerRef.current.src([
                {
                    src,
                    type: HLS_SOURCE_TYPE,
                },
            ]);
            playerRef.current.load();

            return;
        }

        playerRef.current.pause();
        playerRef.current.src([]);
    }, [onPlaybackError, onProgressUpdate, poster, src]);

    useEffect(() => {
        if (!containerRef.current) {
            return undefined;
        }

        let cancelled = false;

        const bootPlayer = async () => {
            try {
                const module = await import('video.js');
                const videojs = module.default ?? module;

                if (cancelled || !containerRef.current || playerRef.current) {
                    return;
                }

                const videoElement = document.createElement('video-js');
                videoElement.className =
                    'video-js vjs-big-play-centered overflow-hidden rounded-[24px]';
                // videoElement.setAttribute('referrerpolicy', 'no-referrer');
                containerRef.current.appendChild(videoElement);

                const player = videojs(videoElement, {
                    autoplay: false,
                    controls: true,
                    fluid: true,
                    preload: 'auto',
                    responsive: true,
                    playsinline: true,
                    poster: latestPosterRef.current ?? undefined,
                    sources: latestSourceRef.current
                        ? [
                              {
                                  src: latestSourceRef.current,
                                  type: HLS_SOURCE_TYPE,
                              },
                          ]
                        : [],
                });

                player.on('error', () => {
                    const playerError = player.error();
                    const debugPayload = {
                        message:
                            playerError?.message ??
                            'The lesson video could not be loaded from Bunny Stream.',
                        code: playerError?.code ?? null,
                        type: playerError?.type ?? null,
                        src: player.currentSrc() || latestSourceRef.current || null,
                        networkState:
                            typeof player.networkState === 'function'
                                ? player.networkState()
                                : null,
                        readyState:
                            typeof player.readyState === 'function'
                                ? player.readyState()
                                : null,
                    };

                    console.error('Video.js playback error', debugPayload, playerError);
                    latestPlaybackErrorHandlerRef.current?.(debugPayload);
                });

                player.on('loadedmetadata', () => {
                    lastReportedProgressRef.current = 0;
                    latestPlaybackErrorHandlerRef.current?.(null);
                });

                player.on('timeupdate', () => {
                    const duration = player.duration();
                    const currentTime = player.currentTime();

                    if (!Number.isFinite(duration) || duration <= 0 || !Number.isFinite(currentTime)) {
                        return;
                    }

                    const progress = Math.max(
                        0,
                        Math.min(100, Math.round((currentTime / duration) * 100)),
                    );

                    if (
                        progress >= 100 ||
                        progress - lastReportedProgressRef.current >= 5
                    ) {
                        lastReportedProgressRef.current = progress;
                        latestProgressHandlerRef.current?.(progress);
                    }
                });

                player.on('ended', () => {
                    lastReportedProgressRef.current = 100;
                    latestProgressHandlerRef.current?.(100);
                });

                playerRef.current = player;
                setLoadFailed(false);
            } catch (error) {
                if (!cancelled) {
                    console.error('Failed to load video.js for lesson playback.', error);
                    setLoadFailed(true);
                }
            }
        };

        void bootPlayer();

        return () => {
            cancelled = true;

            if (playerRef.current) {
                playerRef.current.dispose();
                playerRef.current = null;
            }
        };
    }, []);

    if (loadFailed) {
        return (
            <div
                className={`flex aspect-video items-center justify-center rounded-[24px] border border-white/10 bg-black/30 px-6 py-10 text-center text-sm text-white/70 ${className}`}
            >
                Video player could not be loaded yet. Refresh the page after the Vite cache finishes updating.
            </div>
        );
    }

    return (
        <div data-vjs-player className={className}>
            <div ref={containerRef} />
        </div>
    );
}
