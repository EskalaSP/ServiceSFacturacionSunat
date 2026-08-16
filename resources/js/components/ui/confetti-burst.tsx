import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

const COLORS = ['#F0990A', '#3599E6', '#00BA5D', '#8B5CF6', '#E63946'];

type Piece = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    size: number;
    color: string;
    rot: number;
    vrot: number;
    life: number;
};

/**
 * Confeti suave y elegante, SIN dependencias. Se dibuja en un <canvas> a pantalla
 * completa mediante un portal a document.body, con pointer-events:none, así que
 * nunca interfiere con modales, focus ni clics. Cae unos segundos y se limpia.
 */
export function ConfettiBurst({ show }: { show: boolean }) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);

    useEffect(() => {
        if (!show) return;
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        let raf = 0;
        let running = true;
        const dpr = window.devicePixelRatio || 1;

        const resize = () => {
            canvas.width = window.innerWidth * dpr;
            canvas.height = window.innerHeight * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        };
        resize();
        window.addEventListener('resize', resize);

        const W = () => window.innerWidth;
        const H = () => window.innerHeight;

        // Burst inicial desde la parte superior, distribuido en el ancho.
        const pieces: Piece[] = Array.from({ length: 150 }, () => ({
            x: Math.random() * W(),
            y: -20 - Math.random() * H() * 0.3,
            vx: (Math.random() - 0.5) * 1.6,
            vy: 1.5 + Math.random() * 2.5,
            size: 5 + Math.random() * 6,
            color: COLORS[Math.floor(Math.random() * COLORS.length)],
            rot: Math.random() * Math.PI,
            vrot: (Math.random() - 0.5) * 0.2,
            life: 1,
        }));

        const start = performance.now();
        const DURATION = 4200; // ms

        const tick = (now: number) => {
            if (!running) return;
            const elapsed = now - start;
            ctx.clearRect(0, 0, W(), H());

            for (const p of pieces) {
                p.vy += 0.03; // gravedad suave
                p.x += p.vx;
                p.y += p.vy;
                p.rot += p.vrot;
                p.vx += Math.sin(p.y * 0.01) * 0.02; // deriva tipo hoja
                if (elapsed > DURATION - 900) p.life -= 0.02; // desvanecido final

                ctx.save();
                ctx.globalAlpha = Math.max(0, p.life);
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                ctx.restore();
            }

            if (elapsed < DURATION) {
                raf = requestAnimationFrame(tick);
            } else {
                ctx.clearRect(0, 0, W(), H());
            }
        };
        raf = requestAnimationFrame(tick);

        return () => {
            running = false;
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', resize);
        };
    }, [show]);

    if (!show) return null;

    return createPortal(
        <canvas
            ref={canvasRef}
            style={{
                position: 'fixed',
                inset: 0,
                width: '100vw',
                height: '100vh',
                pointerEvents: 'none',
                zIndex: 9999,
            }}
        />,
        document.body,
    );
}
