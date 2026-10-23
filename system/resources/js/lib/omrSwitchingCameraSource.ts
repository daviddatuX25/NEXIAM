import type { OmrCameraSource, OmrCameraSourceConfig, OmrCameraSourceEvent, OmrCaptureOperation, OmrCapturedFrame } from './omrCaptureProtocol';

/** Retains one lifecycle listener while fencing events from replaced camera adapters. */
export class SwitchingOmrCameraSource implements OmrCameraSource {
    private listener: ((event: OmrCameraSourceEvent) => void) | null = null;
    private signal: AbortSignal | undefined;
    private generation = 0;
    private config: OmrCameraSourceConfig | null = null;
    private preview: HTMLVideoElement | null = null;

    constructor(
        private source: OmrCameraSource,
        private readonly onUiEvent: (event: OmrCameraSourceEvent) => void,
    ) {}

    get id(): string { return this.source.id; }
    get kind(): 'local' | 'paired_phone' { return this.source.kind; }
    get supportsQrCommands(): boolean {
        return (this.source as OmrCameraSource & { supportsQrCommands?: boolean }).supportsQrCommands === true;
    }

    private forward(event: OmrCameraSourceEvent, generation: number): void {
        if (generation !== this.generation) return;
        this.listener?.(event);
        this.onUiEvent(event);
    }

    private async connectSource(generation: number): Promise<void> {
        try {
            await this.source.connect((event) => this.forward(event, generation), this.signal);
            if (generation === this.generation && this.config) {
                await this.source.configure(this.config);
            }
        } catch (error: unknown) {
            this.forward({ type: 'status', status: 'error', message: error instanceof Error ? error.message : 'The camera source could not connect.' }, generation);
            throw error;
        }
    }

    async connect(listener: (event: OmrCameraSourceEvent) => void, signal?: AbortSignal): Promise<void> {
        this.listener = listener;
        this.signal = signal;
        await this.connectSource(++this.generation);
    }

    attachPreview(video: HTMLVideoElement | null): void {
        this.preview = video;
        this.source.attachPreview(video);
    }

    async configure(config: OmrCameraSourceConfig): Promise<void> {
        this.config = config;
        await this.source.configure(config);
    }

    acquire(operation: OmrCaptureOperation, signal?: AbortSignal): Promise<OmrCapturedFrame> {
        return this.source.acquire(operation, signal);
    }

    async replace(source: OmrCameraSource, config: OmrCameraSourceConfig | null): Promise<void> {
        this.forward({ type: 'status', status: 'disconnected' }, this.generation);
        this.generation += 1;
        this.source.disconnect();
        this.source = source;
        this.config = config;
        source.attachPreview(this.preview);
        if (!this.listener || this.signal?.aborted) return;
        await this.connectSource(this.generation);
    }

    disconnect(): void {
        this.generation += 1;
        this.source.disconnect();
        this.listener = null;
        this.signal = undefined;
        this.preview = null;
    }
}
