import { useEffect, useRef, useState } from 'react';
import 'video.js/dist/video-js.css';

const HLS_SOURCE_TYPE = 'application/vnd.apple.mpegurl';

export default function VideoJsPlayer({
    src,
    poster = null,
    className = '',
    autoplay = false,
    hideProgressHandle = false,
    onPlaybackError = null,
    onProgressUpdate = null,
    onTimeUpdate = null,
}) {
    const containerRef = useRef(null);
    const playerRef = useRef(null);
    const latestSourceRef = useRef(src);
    const latestPosterRef = useRef(poster);
    const latestAutoplayRef = useRef(autoplay);
    const latestPlaybackErrorHandlerRef = useRef(onPlaybackError);
    const latestProgressHandlerRef = useRef(onProgressUpdate);
    const latestTimeUpdateHandlerRef = useRef(onTimeUpdate);
    const lastReportedProgressRef = useRef(0);
    const [loadFailed, setLoadFailed] = useState(false);

    useEffect(() => {
        latestPlaybackErrorHandlerRef.current = onPlaybackError;
        latestProgressHandlerRef.current = onProgressUpdate;
        latestTimeUpdateHandlerRef.current = onTimeUpdate;
        latestAutoplayRef.current = autoplay;
    }, [autoplay, hideProgressHandle, onPlaybackError, onProgressUpdate, onTimeUpdate]);

    useEffect(() => {
        latestSourceRef.current = src;
        latestPosterRef.current = poster;

        if (!playerRef.current) {
            return;
        }

        playerRef.current.poster(poster ?? '');

        if (src) {
            const attemptAutoplay = () => {
                if (!latestAutoplayRef.current) {
                    return;
                }

                const playbackResult = playerRef.current?.play?.();

                if (playbackResult && typeof playbackResult.catch === 'function') {
                    playbackResult.catch(() => {});
                }
            };

            playerRef.current.src([
                {
                    src,
                    type: HLS_SOURCE_TYPE,
                },
            ]);
            playerRef.current.one('loadedmetadata', attemptAutoplay);
            playerRef.current.load();

            return;
        }

        playerRef.current.pause();
        playerRef.current.src([]);
    }, [poster, src]);

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
                    autoplay: latestAutoplayRef.current,
                    controls: true,
                    fluid: false,
                    fill: true,
                    preload: 'auto',
                    responsive: false,
                    playsinline: true,
                    poster: latestPosterRef.current ?? undefined,
                    controlBar: {
                        playToggle: true,
                        muteToggle: true,
                        currentTimeDisplay: true,
                        timeDivider: true,
                        durationDisplay: true,
                        progressControl: true,
                        fullscreenToggle: true,
                    },
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

                    if (latestAutoplayRef.current) {
                        const playbackResult = player.play();

                        if (playbackResult && typeof playbackResult.catch === 'function') {
                            playbackResult.catch(() => {});
                        }
                    }
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

                    latestTimeUpdateHandlerRef.current?.({
                        currentTime,
                        duration,
                        remainingSeconds: Math.max(0, duration - currentTime),
                        isEnded: false,
                    });
                });

                player.on('ended', () => {
                    lastReportedProgressRef.current = 100;
                    latestProgressHandlerRef.current?.(100);
                    latestTimeUpdateHandlerRef.current?.({
                        currentTime: player.duration(),
                        duration: player.duration(),
                        remainingSeconds: 0,
                        isEnded: true,
                    });
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
            <style>
                {`
                    .yogafx-video-shell .video-js {
                        width: 100% !important;
                        height: 100% !important;
                        border-radius: 5px;
                        overflow: hidden;
                        background: #000;
                    }

                    .yogafx-video-shell .video-js .vjs-tech {
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                    }

                    .yogafx-video-shell .video-js .vjs-control-bar {
                        display: flex;
                        flex-wrap: wrap;
                        align-items: center;
                        gap: 0;
                        height: 76px;
                        padding: 12px 12px 10px;
                        background: linear-gradient(to top, rgba(0, 0, 0, 0.88), rgba(0, 0, 0, 0.52));
                    }

                    .yogafx-video-shell .video-js .vjs-button > .vjs-icon-placeholder::before,
                    .yogafx-video-shell .video-js .vjs-time-control,
                    .yogafx-video-shell .video-js .vjs-time-divider {
                        color: rgba(255, 255, 255, 0.95);
                    }

                    .yogafx-video-shell .video-js .vjs-control {
                        height: 28px;
                    }

                    .yogafx-video-shell .video-js .vjs-play-control,
                    .yogafx-video-shell .video-js .vjs-mute-control,
                    .yogafx-video-shell .video-js .vjs-current-time,
                    .yogafx-video-shell .video-js .vjs-time-divider,
                    .yogafx-video-shell .video-js .vjs-duration,
                    .yogafx-video-shell .video-js .vjs-fullscreen-control {
                        order: 1;
                        display: flex !important;
                        align-items: center;
                        justify-content: center;
                        flex: 0 0 auto;
                    }

                    .yogafx-video-shell .video-js .vjs-play-control,
                    .yogafx-video-shell .video-js .vjs-mute-control,
                    .yogafx-video-shell .video-js .vjs-fullscreen-control {
                        width: 32px;
                    }

                    .yogafx-video-shell .video-js .vjs-current-time,
                    .yogafx-video-shell .video-js .vjs-duration,
                    .yogafx-video-shell .video-js .vjs-time-divider {
                        font-size: 12px;
                        line-height: 1;
                    }

                    .yogafx-video-shell .video-js .vjs-current-time {
                        margin-left: 8px;
                        padding-left: 0;
                    }

                    .yogafx-video-shell .video-js .vjs-duration {
                        padding-left: 0;
                    }

                    .yogafx-video-shell .video-js .vjs-time-divider {
                        min-width: auto;
                        padding: 0 2px;
                    }

                    .yogafx-video-shell .video-js .vjs-fullscreen-control {
                        margin-left: auto;
                    }

                    .yogafx-video-shell .video-js .vjs-progress-control {
                        order: 2;
                        flex: 0 0 100%;
                        width: 100%;
                        height: 14px;
                        margin-top: 10px;
                        min-width: 100%;
                    }

                    .yogafx-video-shell .video-js .vjs-progress-holder {
                        margin: 0;
                        height: 4px;
                    }

                    .yogafx-video-shell .video-js .vjs-load-progress,
                    .yogafx-video-shell .video-js .vjs-load-progress div {
                        background: rgba(255, 255, 255, 0.18);
                    }

                    .yogafx-video-shell .video-js .vjs-play-progress {
                        background: #db202c;
                    }

                    .yogafx-video-shell .video-js .vjs-slider {
                        background: rgba(255, 255, 255, 0.22);
                    }

                    .yogafx-video-shell .video-js .vjs-play-progress::before,
                    .yogafx-video-shell .video-js .vjs-slider-handle {
                        color: #ffffff;
                    }

                    .yogafx-video-shell .video-js .vjs-big-play-button {
                        top: 50%;
                        left: 50%;
                        width: 70px;
                        height: 70px;
                        border: 1px solid rgba(255, 255, 255, 0.24);
                        border-radius: 9999px;
                        background: rgba(0, 0, 0, 0.42);
                        transform: translate(-50%, -50%);
                    }

                    .yogafx-video-shell .video-js .vjs-big-play-button .vjs-icon-placeholder::before {
                        font-size: 32px;
                        line-height: 68px;
                    }

                    .yogafx-video-shell.hide-progress-handle .video-js .vjs-play-progress::before,
                    .yogafx-video-shell.hide-progress-handle .video-js .vjs-slider-handle {
                        opacity: 0 !important;
                    }
                `}
            </style>
            <div
                ref={containerRef}
                className={[
                    'yogafx-video-shell h-full w-full',
                    hideProgressHandle ? 'hide-progress-handle' : '',
                ].join(' ')}
            />
        </div>
    );
}
