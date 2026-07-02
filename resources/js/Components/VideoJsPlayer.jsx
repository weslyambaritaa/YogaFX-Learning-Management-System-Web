import {
    Maximize,
    Pause,
    Play,
    RotateCcw,
    RotateCw,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import 'video.js/dist/video-js.css';

const HLS_SOURCE_TYPE = 'application/vnd.apple.mpegurl';
const CONTROL_HIDE_DELAY_MS = 3000;
const SEEK_STEP_SECONDS = 10;

function formatDuration(secondsValue) {
    const safeSeconds = Math.max(0, Math.floor(Number(secondsValue) || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    if (hours > 0) {
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function BigVideoActionButton({
    icon: Icon,
    label,
    onClick,
    size = 'default',
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className={[
                'relative flex items-center justify-center rounded-full text-white transition hover:text-white/80',
                size === 'primary' ? 'h-16 w-16' : 'h-12 w-12',
            ].join(' ')}
        >
            <Icon className={size === 'primary' ? 'size-8' : 'size-5'} />
            {size !== 'primary' ? (
                <span className="absolute -bottom-1.5 px-1.5 py-0.5 text-[9px] font-semibold leading-none text-white/85">
                    10
                </span>
            ) : null}
        </button>
    );
}

function SmallControlButton({ icon: Icon, label, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="flex h-8 w-8 items-center justify-center text-white transition hover:text-white/80"
        >
            <Icon className="size-4" />
        </button>
    );
}

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
    const controlsTimerRef = useRef(null);
    const [loadFailed, setLoadFailed] = useState(false);
    const [isReady, setIsReady] = useState(false);
    const [controlsVisible, setControlsVisible] = useState(true);
    const [isPlaying, setIsPlaying] = useState(false);
    const [isMuted, setIsMuted] = useState(false);
    const [duration, setDuration] = useState(0);
    const [currentTime, setCurrentTime] = useState(0);

    const clearControlsTimer = () => {
        if (controlsTimerRef.current) {
            window.clearTimeout(controlsTimerRef.current);
            controlsTimerRef.current = null;
        }
    };

    const scheduleControlsHide = () => {
        clearControlsTimer();

        if (!isPlaying) {
            return;
        }

        controlsTimerRef.current = window.setTimeout(() => {
            setControlsVisible(false);
        }, CONTROL_HIDE_DELAY_MS);
    };

    const syncPlayerState = () => {
        const player = playerRef.current;

        if (!player) {
            return;
        }

        const nextDuration = Number(player.duration()) || 0;
        const nextCurrentTime = Number(player.currentTime()) || 0;
        const nextIsPlaying = !player.paused();
        const nextIsMuted = player.muted();

        setDuration(nextDuration);
        setCurrentTime(nextCurrentTime);
        setIsPlaying(nextIsPlaying);
        setIsMuted(nextIsMuted);
        setIsReady(true);

        if (nextIsPlaying) {
            scheduleControlsHide();
        } else {
            clearControlsTimer();
            setControlsVisible(true);
        }
    };

    const showControls = () => {
        setControlsVisible(true);
        scheduleControlsHide();
    };

    const toggleControlsVisibility = () => {
        if (controlsVisible) {
            clearControlsTimer();
            setControlsVisible(false);
            return;
        }

        showControls();
    };

    const runControlAction = async (action) => {
        showControls();
        await action();
        syncPlayerState();
    };

    const seekTo = async (nextTime) => {
        const player = playerRef.current;

        if (!player) {
            return;
        }

        const boundedTime = Math.max(0, Math.min(duration || 0, nextTime));
        player.currentTime(boundedTime);
        syncPlayerState();
    };

    useEffect(() => {
        latestPlaybackErrorHandlerRef.current = onPlaybackError;
        latestProgressHandlerRef.current = onProgressUpdate;
        latestTimeUpdateHandlerRef.current = onTimeUpdate;
        latestAutoplayRef.current = autoplay;
    }, [autoplay, onPlaybackError, onProgressUpdate, onTimeUpdate]);

    useEffect(() => {
        latestSourceRef.current = src;
        latestPosterRef.current = poster;

        if (!playerRef.current) {
            return;
        }

        playerRef.current.poster(poster ?? '');
        setIsReady(false);
        setCurrentTime(0);
        setDuration(0);

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
                    'video-js overflow-hidden rounded-[24px]';
                containerRef.current.appendChild(videoElement);

                const player = videojs(videoElement, {
                    autoplay: latestAutoplayRef.current,
                    controls: false,
                    fluid: false,
                    fill: true,
                    preload: 'auto',
                    responsive: false,
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
                    syncPlayerState();

                    if (latestAutoplayRef.current) {
                        const playbackResult = player.play();

                        if (playbackResult && typeof playbackResult.catch === 'function') {
                            playbackResult.catch(() => {});
                        }
                    }
                });

                player.on('play', syncPlayerState);
                player.on('pause', syncPlayerState);
                player.on('volumechange', syncPlayerState);
                player.on('fullscreenchange', syncPlayerState);

                player.on('timeupdate', () => {
                    const nextDuration = Number(player.duration()) || 0;
                    const nextCurrentTime = Number(player.currentTime()) || 0;
                    setDuration(nextDuration);
                    setCurrentTime(nextCurrentTime);
                    setIsPlaying(!player.paused());
                    setIsMuted(player.muted());

                    if (!Number.isFinite(nextDuration) || nextDuration <= 0) {
                        return;
                    }

                    const progress = Math.max(
                        0,
                        Math.min(100, Math.round((nextCurrentTime / nextDuration) * 100)),
                    );

                    if (
                        progress >= 100 ||
                        progress - lastReportedProgressRef.current >= 5
                    ) {
                        lastReportedProgressRef.current = progress;
                        latestProgressHandlerRef.current?.(progress);
                    }

                    latestTimeUpdateHandlerRef.current?.({
                        currentTime: nextCurrentTime,
                        duration: nextDuration,
                        remainingSeconds: Math.max(0, nextDuration - nextCurrentTime),
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
                    syncPlayerState();
                    setControlsVisible(true);
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
            clearControlsTimer();

            if (playerRef.current) {
                playerRef.current.dispose();
                playerRef.current = null;
            }
        };
    }, []);

    useEffect(() => {
        scheduleControlsHide();
        return clearControlsTimer;
    }, [isPlaying]);

    if (loadFailed) {
        return (
            <div
                className={`flex aspect-video items-center justify-center rounded-[24px] border border-white/10 bg-black/30 px-6 py-10 text-center text-sm text-white/70 ${className}`}
            >
                Video player could not be loaded yet. Refresh the page after the Vite cache finishes updating.
            </div>
        );
    }

    const progressPercentage =
        duration > 0 ? Math.max(0, Math.min(100, (currentTime / duration) * 100)) : 0;

    return (
        <div data-vjs-player className={className}>
            <style>
                {`
                    .yogafx-video-shell {
                        position: relative;
                        width: 100%;
                        height: 100%;
                        overflow: hidden;
                        border-radius: 5px;
                        background: #000;
                    }

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

                    .yogafx-video-shell .yogafx-video-slider {
                        -webkit-appearance: none;
                        appearance: none;
                        width: 100%;
                        height: 2.5px;
                        border-radius: 9999px;
                        background: linear-gradient(
                            to right,
                            #db202c 0%,
                            #db202c var(--progress-percent),
                            rgba(255,255,255,0.24) var(--progress-percent),
                            rgba(255,255,255,0.24) 100%
                        );
                        outline: none;
                    }

                    .yogafx-video-shell .yogafx-video-slider::-webkit-slider-thumb {
                        -webkit-appearance: none;
                        appearance: none;
                        width: 10px;
                        height: 10px;
                        border-radius: 9999px;
                        background: #db202c;
                        border: 0;
                    }

                    .yogafx-video-shell.hide-progress-handle .yogafx-video-slider::-webkit-slider-thumb {
                        opacity: 0;
                    }

                    .yogafx-video-shell .yogafx-video-slider::-moz-range-thumb {
                        width: 10px;
                        height: 10px;
                        border-radius: 9999px;
                        background: #db202c;
                        border: 0;
                    }

                    .yogafx-video-shell.hide-progress-handle .yogafx-video-slider::-moz-range-thumb {
                        opacity: 0;
                    }
                `}
            </style>

            <div
                ref={containerRef}
                className={[
                    'yogafx-video-shell',
                    hideProgressHandle ? 'hide-progress-handle' : '',
                ].join(' ')}
            >
                {isReady ? null : (
                    <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-black/40">
                        <div className="h-9 w-9 animate-spin rounded-full border-2 border-white/25 border-t-[#db202c]" />
                    </div>
                )}

                <div className="pointer-events-none absolute inset-0 z-20">
                    <div
                        className={[
                            'absolute inset-0 transition-opacity duration-200',
                            controlsVisible ? 'opacity-100' : 'opacity-0',
                        ].join(' ')}
                        style={{
                            background:
                                'linear-gradient(to bottom, rgba(0,0,0,0.4), rgba(0,0,0,0.08) 34%, rgba(0,0,0,0.56) 100%)',
                        }}
                    />

                    {!controlsVisible ? (
                        <div className="absolute inset-x-0 bottom-0 h-4">
                            <div className="absolute inset-x-0 bottom-0 h-[3px] bg-white/24">
                                <div
                                    className="h-full bg-[#db202c]"
                                    style={{ width: `${progressPercentage}%` }}
                                />
                            </div>
                        </div>
                    ) : null}
                </div>

                {!controlsVisible ? (
                    <button
                        type="button"
                        aria-label="Show video controls"
                        onClick={toggleControlsVisibility}
                        className="absolute inset-0 z-[35] cursor-default bg-transparent"
                    />
                ) : null}

                <div
                    className={[
                        'absolute inset-0 z-30 pointer-events-none transition-opacity duration-200',
                        controlsVisible ? 'opacity-100' : 'pointer-events-none opacity-0',
                    ].join(' ')}
                >
                    <button
                        type="button"
                        aria-label="Hide video controls"
                        onClick={toggleControlsVisibility}
                        className="pointer-events-auto absolute inset-0 bg-transparent"
                    />

                    <div className="relative flex h-full flex-col justify-between p-3 sm:p-4">
                        <div />

                        <div
                            className="pointer-events-auto flex items-center justify-center gap-4 sm:gap-[18px]"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <BigVideoActionButton
                                icon={RotateCcw}
                                label="Skip backward 10 seconds"
                                onClick={() =>
                                    runControlAction(() => seekTo(currentTime - SEEK_STEP_SECONDS))
                                }
                            />
                            <BigVideoActionButton
                                icon={isPlaying ? Pause : Play}
                                label={isPlaying ? 'Pause video' : 'Play video'}
                                size="primary"
                                onClick={() =>
                                    runControlAction(async () => {
                                        const player = playerRef.current;
                                        if (!player) {
                                            return;
                                        }

                                        if (player.paused()) {
                                            const playbackResult = player.play();
                                            if (
                                                playbackResult &&
                                                typeof playbackResult.catch === 'function'
                                            ) {
                                                playbackResult.catch(() => {});
                                            }
                                            return;
                                        }

                                        player.pause();
                                    })
                                }
                            />
                            <BigVideoActionButton
                                icon={RotateCw}
                                label="Skip forward 10 seconds"
                                onClick={() =>
                                    runControlAction(() => seekTo(currentTime + SEEK_STEP_SECONDS))
                                }
                            />
                        </div>

                        <div
                            className="pointer-events-auto px-1 pb-1 pt-4"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <div className="mb-1.5 flex items-center">
                                <SmallControlButton
                                    icon={isPlaying ? Pause : Play}
                                    label={isPlaying ? 'Pause video' : 'Play video'}
                                    onClick={() =>
                                        runControlAction(async () => {
                                            const player = playerRef.current;
                                            if (!player) {
                                                return;
                                            }

                                            if (player.paused()) {
                                                const playbackResult = player.play();
                                                if (
                                                    playbackResult &&
                                                    typeof playbackResult.catch === 'function'
                                                ) {
                                                    playbackResult.catch(() => {});
                                                }
                                                return;
                                            }

                                            player.pause();
                                        })
                                    }
                                />
                                <SmallControlButton
                                    icon={isMuted ? VolumeX : Volume2}
                                    label={isMuted ? 'Unmute video' : 'Mute video'}
                                    onClick={() =>
                                        runControlAction(async () => {
                                            const player = playerRef.current;
                                            if (!player) {
                                                return;
                                            }

                                            player.muted(!player.muted());
                                        })
                                    }
                                />
                                <div className="flex-1 text-center text-[11px] font-semibold text-white sm:text-xs">
                                    {formatDuration(currentTime)} / {formatDuration(duration)}
                                </div>
                                <SmallControlButton
                                    icon={Maximize}
                                    label="Open fullscreen"
                                    onClick={() =>
                                        runControlAction(async () => {
                                            const player = playerRef.current;
                                            if (!player) {
                                                return;
                                            }

                                            if (typeof player.requestFullscreen === 'function') {
                                                player.requestFullscreen();
                                            }
                                        })
                                    }
                                />
                            </div>

                            <input
                                type="range"
                                min={0}
                                max={duration || 0}
                                step="0.1"
                                value={Math.min(currentTime, duration || 0)}
                                onChange={(event) => {
                                    void seekTo(Number(event.target.value));
                                }}
                                onInput={() => showControls()}
                                className="yogafx-video-slider"
                                style={{ '--progress-percent': `${progressPercentage}%` }}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
