import { useEffect, useRef, useState } from 'react';
import 'video.js/dist/video-js.css';

const HLS_SOURCE_TYPE = 'application/vnd.apple.mpegurl';
const SKIP_SECONDS = 30;

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
    const [isPaused, setIsPaused] = useState(true);

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
                    'video-js vjs-big-play-centered overflow-hidden rounded-[5px]';
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
                        volumePanel: true,
                        currentTimeDisplay: true,
                        timeDivider: true,
                        durationDisplay: true,
                        progressControl: true,
                        skipButtons: {
                            backward: SKIP_SECONDS,
                            forward: SKIP_SECONDS,
                        },
                        pictureInPictureToggle: true,
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

                player.on('play', () => setIsPaused(false));
                player.on('pause', () => setIsPaused(true));

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

    const handleCenterToggle = () => {
        if (!playerRef.current) return;
        if (playerRef.current.paused()) {
            playerRef.current.play();
        } else {
            playerRef.current.pause();
        }
    };

    if (loadFailed) {
        return (
            <div
                className={`flex aspect-video items-center justify-center rounded-[5px] border border-white/10 bg-black/30 px-6 py-10 text-center text-sm text-white/70 ${className}`}
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
                        font-family: 'Montserrat', sans-serif;
                    }

                    .yogafx-video-shell .video-js .vjs-tech {
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                    }

                    /* Sembunyikan play toggle bawaan di control bar, karena diganti tombol tengah custom */
                    .yogafx-video-shell .video-js .vjs-control-bar .vjs-play-control {
                        display: none;
                    }

                    /* Control bar bawah: gradasi gelap, rounded */
                    .yogafx-video-shell .video-js .vjs-control-bar {
                        background: linear-gradient(to top, rgba(0,0,0,0.85), rgba(0,0,0,0));
                        height: 3em;
                    }

                    /* Progress bar merah khas YogaFX */
                    .yogafx-video-shell .video-js .vjs-progress-control .vjs-play-progress {
                        background-color: #dc2626;
                    }
                    .yogafx-video-shell .video-js .vjs-progress-control .vjs-load-progress div {
                        background: rgba(255,255,255,0.25);
                    }
                    .yogafx-video-shell .video-js .vjs-progress-holder {
                        background: rgba(255,255,255,0.15);
                        height: 0.4em;
                    }
                    .yogafx-video-shell .video-js .vjs-play-progress::before {
                        color: #dc2626;
                    }

                    /* Tombol skip mundur/maju 30 detik - dibuat lingkaran seperti referensi */
                    .yogafx-video-shell .video-js [class*="vjs-skip-backward"],
                    .yogafx-video-shell .video-js [class*="vjs-skip-forward"] {
                        position: absolute;
                        top: 50%;
                        transform: translateY(-50%);
                        width: 3em;
                        height: 3em;
                        border-radius: 9999px;
                        background: rgba(0, 0, 0, 0.45);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .yogafx-video-shell .video-js [class*="vjs-skip-backward"] {
                        left: calc(50% - 4.5em);
                    }
                    .yogafx-video-shell .video-js [class*="vjs-skip-forward"] {
                        left: calc(50% + 1.5em);
                    }
                    .yogafx-video-shell .video-js [class*="vjs-skip-backward"]:hover,
                    .yogafx-video-shell .video-js [class*="vjs-skip-forward"]:hover {
                        background: rgba(220, 38, 38, 0.55);
                    }

                    /* Tombol play/pause custom di tengah video */
                    .yogafx-video-shell .yogafx-center-toggle {
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 4em;
                        height: 4em;
                        border-radius: 9999px;
                        background: rgba(0, 0, 0, 0.5);
                        border: none;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        z-index: 20;
                        transition: background 0.2s ease;
                    }
                    .yogafx-video-shell .yogafx-center-toggle:hover {
                        background: rgba(220, 38, 38, 0.55);
                    }
                    .yogafx-video-shell .yogafx-center-toggle svg {
                        width: 1.8em;
                        height: 1.8em;
                        fill: #fff;
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
                    'yogafx-video-shell relative h-full w-full',
                    hideProgressHandle ? 'hide-progress-handle' : '',
                ].join(' ')}
            >
                <button
                    type="button"
                    className="yogafx-center-toggle"
                    onClick={handleCenterToggle}
                    aria-label={isPaused ? 'Play' : 'Pause'}
                >
                    {isPaused ? (
                        <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
                    ) : (
                        <svg viewBox="0 0 24 24"><path d="M6 5h4v14H6zM14 5h4v14h-4z" /></svg>
                    )}
                </button>
            </div>
        </div>
    );
}