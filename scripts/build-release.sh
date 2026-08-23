#!/usr/bin/env bash
#
# Empaqueta una copia del sistema para entregar a un comprador:
#   1. Exporta el codigo (solo archivos versionados).
#   2. Sella el fingerprint de la licencia en la copia.
#   3. Ofusca los archivos del candado (gratis, con yakpro-po).
#   4. Quita los archivos internos que no deben entregarse.
#
# Uso:
#   scripts/build-release.sh <fingerprint> [dir_salida]
#
# El <fingerprint> lo da el panel de licencias al emitir la licencia.
# Requiere: git, php, perl, tar y conexion (la 1a vez descarga yakpro-po).
#
set -euo pipefail

FP="${1:-}"
OUT="${2:-dist}"

if [ -z "$FP" ]; then
    echo "Uso: scripts/build-release.sh <fingerprint> [dir_salida]"
    exit 1
fi
if ! echo "$FP" | grep -qE '^[A-Za-z0-9_-]{6,64}$'; then
    echo "Fingerprint invalido: 6-64 caracteres (letras, numeros, _ o -)."
    exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# En Windows (git-bash) PHP es nativo y no entiende rutas POSIX (/tmp, /c/...).
# cygpath -m las pasa a formato C:/... ; en Linux no existe y se usa tal cual.
win() { cygpath -m "$1" 2>/dev/null || echo "$1"; }

TOOLS="$ROOT/tools/yakpro-po"

# 1. Instalar yakpro-po la primera vez.
if [ ! -f "$TOOLS/yakpro-po.php" ]; then
    echo "==> Instalando yakpro-po (una sola vez)..."
    mkdir -p "$ROOT/tools"
    git clone --depth 1 https://github.com/pk-fr/yakpro-po.git "$TOOLS"
    git clone --depth 1 https://github.com/nikic/PHP-Parser.git "$TOOLS/PHP-Parser"
fi

# 2. Export limpio del repo (solo lo versionado; excluye .env, vendor, node_modules).
echo "==> Exportando codigo a $OUT/ ..."
rm -rf "$OUT"
mkdir -p "$OUT"
git archive HEAD | tar -x -C "$OUT"

# 3. Sellar el fingerprint.
echo "==> Sellando fingerprint $FP ..."
perl -i -pe "s/const FINGERPRINT = '[^']*';/const FINGERPRINT = '$FP';/" \
    "$OUT/app/Services/License/BuildInfo.php"
grep -q "const FINGERPRINT = '$FP';" "$OUT/app/Services/License/BuildInfo.php" \
    || { echo "ERROR: no se pudo sellar el fingerprint."; exit 1; }

# 4. Ofuscar los archivos del candado.
echo "==> Ofuscando el candado..."
CRIT=(
    app/Services/License/LicenseClient.php
    app/Services/License/LicenseCheck.php
    app/Services/License/MachineId.php
    app/Services/License/BuildInfo.php
    app/Http/Middleware/CheckLicense.php
    app/Services/Greenter/GreenterService.php
)
TMPSRC="$(mktemp -d)"
TMPOUT="$(mktemp -d)"
for f in "${CRIT[@]}"; do
    mkdir -p "$TMPSRC/$(dirname "$f")"
    cp "$OUT/$f" "$TMPSRC/$f"
done
CNF="$(mktemp)"
cat "$ROOT/scripts/yakpro-laravel.cnf" > "$CNF"
printf "\n\$conf->source_directory='%s';\n\$conf->target_directory='%s';\n" "$(win "$TMPSRC")" "$(win "$TMPOUT")" >> "$CNF"
( cd "$TOOLS" && php yakpro-po.php --config-file "$(win "$CNF")" >/dev/null )
for f in "${CRIT[@]}"; do
    OBF="$TMPOUT/yakpro-po/obfuscated/$f"
    [ -f "$OBF" ] || { echo "ERROR: no se genero $f ofuscado."; exit 1; }
    php -l "$OBF" >/dev/null || { echo "ERROR: $f ofuscado no es PHP valido."; exit 1; }
    cp "$OBF" "$OUT/$f"
done
rm -rf "$TMPSRC" "$TMPOUT" "$CNF"

# 5. Quitar archivos internos que no se entregan.
rm -f "$OUT/LICENSING.md"
rm -rf "$OUT/scripts" "$OUT/tools"

echo ""
echo "OK: copia lista en $OUT/"
echo "    - fingerprint sellado: $FP"
echo "    - candado ofuscado"
echo "Entrega el contenido de $OUT/ (el comprador corre composer install, npm run build y php artisan migrate)."
