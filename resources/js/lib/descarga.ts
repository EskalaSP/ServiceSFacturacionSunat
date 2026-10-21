/** Arma el query string; las listas se envían como `clave[]=a&clave[]=b`. */
export function armarQuery(
    params: Record<string, string | string[] | undefined | null>,
): string {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (Array.isArray(v)) {
            v.forEach((x) => q.append(`${k}[]`, x));
        } else if (v) {
            q.append(k, v);
        }
    }
    return q.toString();
}

/** Descarga un archivo vía fetch para poder mostrar el mensaje real si el servidor responde con error. */
export async function descargarArchivo(
    url: string,
    nombreFallback: string,
): Promise<{ ok: true } | { ok: false; error: string }> {
    let res: Response;
    try {
        res = await fetch(url, {
            headers: { Accept: 'application/json, */*', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
    } catch {
        return { ok: false, error: 'No se pudo conectar con el servidor.' };
    }

    if (!res.ok) {
        let error = `Error ${res.status} al generar el archivo.`;
        try {
            const j = await res.json();
            const primero = j?.errors ? Object.values(j.errors as Record<string, string[]>)[0]?.[0] : undefined;
            error = primero ?? j?.message ?? j?.mensaje ?? j?.error ?? error;
        } catch {
            // respuesta sin JSON: se deja el mensaje genérico
        }
        return { ok: false, error };
    }

    const cd = res.headers.get('Content-Disposition') ?? '';
    const nombre = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd)?.[1] ?? nombreFallback;
    const blob = await res.blob();
    const href = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = href;
    a.download = decodeURIComponent(nombre);
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(href);

    return { ok: true };
}
