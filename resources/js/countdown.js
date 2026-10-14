export function countdown(target) {
    return {
        d: 0, h: 0, m: 0, s: 0, done: false,
        init() {
            const end = new Date(target).getTime();
            const tick = () => {
                const diff = Math.max(0, end - Date.now());
                this.done = diff === 0;
                this.d = Math.floor(diff / 86400000);
                this.h = Math.floor((diff % 86400000) / 3600000);
                this.m = Math.floor((diff % 3600000) / 60000);
                this.s = Math.floor((diff % 60000) / 1000);
            };
            tick();
            setInterval(tick, 1000);
        },
        pad(n) { return String(n).padStart(2, '0'); },
    };
}
