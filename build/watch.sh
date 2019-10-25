dir="$(cd "$(dirname "$0")/.." && pwd)"
echo "watching ${dir}"

inotifywait -r -m "${dir}" 2>/dev/null | while read message; do
    if [ -n "$(echo $message | grep "${dir}/public/")" ]; then
        continue
    fi

    if [ -z "$(echo $message | grep '\.\(js\|css\|png\)$' | grep '\bCLOSE_WRITE\b')" ]; then
        continue
    fi

    echo -n "building..."
    php "${dir}/build/build.php"
    echo "done"
done
