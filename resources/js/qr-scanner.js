/**
 * Scanner QR în browser: BarcodeDetector nativ (Android/Chrome) sau jsQR (fallback, inclusiv iOS).
 * Emite evenimentul `qr-decoded` cu textul decodat.
 */
export function qrScanner() {
    return {
        active: false,
        error: null,
        stream: null,
        lastValue: null,
        lastAt: 0,
        detector: null,
        jsQR: null,

        async start() {
            this.error = null;
            if (!navigator.mediaDevices?.getUserMedia) {
                this.error = this.$el.dataset.errorNoCamera || 'Camera indisponibilă';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            } catch (e) {
                this.error = this.$el.dataset.errorPermission || 'Permisiune cameră refuzată';
                return;
            }
            const video = this.$refs.video;
            video.srcObject = this.stream;
            video.setAttribute('playsinline', 'true');
            await video.play();
            this.active = true;
            if ('BarcodeDetector' in window) {
                try { this.detector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { this.detector = null; }
            }
            if (!this.detector) {
                const mod = await import('jsqr');
                this.jsQR = mod.default;
            }
            this.loop();
        },

        stop() {
            this.active = false;
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
        },

        emit(value) {
            const now = Date.now();
            if (value === this.lastValue && now - this.lastAt < 4000) return;
            this.lastValue = value;
            this.lastAt = now;
            navigator.vibrate?.(60);
            this.$dispatch('qr-decoded', { value });
        },

        async loop() {
            if (!this.active) return;
            const video = this.$refs.video;
            if (video.readyState >= 2) {
                try {
                    if (this.detector) {
                        const codes = await this.detector.detect(video);
                        if (codes[0]?.rawValue) this.emit(codes[0].rawValue);
                    } else if (this.jsQR) {
                        const canvas = this.$refs.canvas;
                        const w = video.videoWidth, h = video.videoHeight;
                        const scale = Math.min(1, 640 / Math.max(w, h));
                        canvas.width = w * scale; canvas.height = h * scale;
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = this.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
                        if (code?.data) this.emit(code.data);
                    }
                } catch (e) { /* cadru ratat — continuăm */ }
            }
            setTimeout(() => requestAnimationFrame(() => this.loop()), 120);
        },

        destroy() { this.stop(); },
    };
}
