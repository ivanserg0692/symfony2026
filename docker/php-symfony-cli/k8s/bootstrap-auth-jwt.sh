#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/../../.." && pwd)"
auth_dir="${repo_root}/symfony"
source "${repo_root}/scripts/lib/k8s-common.sh"
reject_k8s_args k8s:auth:jwt:bootstrap "$@"
ensure_k8s_settings
auth_image="${AUTH_JWT_BOOTSTRAP_IMAGE:-symfony-auth:k8s-$image_profile}"

if [[ ! -f "${auth_dir}/.env" ]]; then
    echo "Auth .env is missing." >&2
    exit 1
fi

if ! command -v openssl >/dev/null 2>&1; then
    echo "OpenSSL is required to validate Auth JWT keys." >&2
    exit 1
fi

if ! command -v kubectl >/dev/null 2>&1; then
    echo "kubectl is required to manage the Auth JWT Secret." >&2
    exit 1
fi

existing_secret="$(kubectl -n "$namespace" get secret auth-jwt --ignore-not-found -o name)"
if [[ -n "$existing_secret" ]]; then
    echo "Secret auth-jwt already exists in namespace ${namespace}; refusing to rotate JWT keys." >&2
    exit 1
fi

private_key="${auth_dir}/config/jwt/private.pem"
public_key="${auth_dir}/config/jwt/public.pem"
if [[ -f "$private_key" && ! -f "$public_key" || ! -f "$private_key" && -f "$public_key" ]]; then
    echo "Only one Auth JWT key exists; refusing to generate a new pair." >&2
    exit 1
fi

temp_dir="$(mktemp -d)"
chmod 700 "$temp_dir"
trap 'rm -f -- "${temp_dir}/passphrase" "${temp_dir}/private-public.der" "${temp_dir}/public.der"; rmdir -- "$temp_dir"' EXIT

dotenv_mounts=()
for env_file in "${auth_dir}"/.env*; do
    if [[ -f "$env_file" ]]; then
        dotenv_mounts+=(--mount "type=bind,source=${env_file},target=/workspace/${env_file##*/},readonly")
    fi
done

get_symfony_env() {
    local variable_name="$1"
    if [[ ! "$variable_name" =~ ^[a-zA-Z_][a-zA-Z0-9_]*$ ]]; then
        echo "Invalid Symfony environment variable name." >&2
        return 2
    fi

    docker run --rm \
        "${dotenv_mounts[@]}" \
        --env "SYMFONY_ENV_NAME=${variable_name}" \
        --entrypoint php "$auth_image" \
        -r '
            require "/workspace/vendor/autoload.php";
            (new Symfony\Component\Dotenv\Dotenv())->bootEnv("/workspace/.env");
            $name = getenv("SYMFONY_ENV_NAME");
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;
            if (!is_string($value) || $value === "") {
                fwrite(STDERR, "Symfony environment variable {$name} is missing.\n");
                exit(1);
            }
            fwrite(STDOUT, $value);
        '
}

jwt_passphrase="$(get_symfony_env JWT_PASSPHRASE)"

printf '%s' "$jwt_passphrase" > "${temp_dir}/passphrase"
chmod 600 "${temp_dir}/passphrase"
unset jwt_passphrase

if [[ ! -f "$private_key" ]]; then
    docker run --rm \
        --mount "type=bind,source=${auth_dir}/config/jwt,target=/workspace/config/jwt" \
        --mount "type=bind,source=${temp_dir}/passphrase,target=/bootstrap-secrets/passphrase,readonly" \
        "${dotenv_mounts[@]}" \
        --entrypoint bash "$auth_image" \
        -c 'set -euo pipefail; JWT_PASSPHRASE="$(cat /bootstrap-secrets/passphrase)"; export JWT_PASSPHRASE; exec bin/init-jwt'
fi

if [[ ! -s "$private_key" || ! -s "$public_key" || ! -s "${temp_dir}/passphrase" ]]; then
    echo "JWT bootstrap did not produce a complete key pair and passphrase." >&2
    exit 1
fi

if ! openssl pkey -in "$private_key" -passin "file:${temp_dir}/passphrase" -pubout -outform DER -out "${temp_dir}/private-public.der" 2>/dev/null \
    || ! openssl pkey -pubin -in "$public_key" -pubout -outform DER -out "${temp_dir}/public.der" 2>/dev/null \
    || ! cmp -s "${temp_dir}/private-public.der" "${temp_dir}/public.der"; then
    echo "JWT keys do not match or JWT_PASSPHRASE cannot unlock private.pem." >&2
    exit 1
fi

kubectl -n "$namespace" create secret generic auth-jwt \
    --from-file="private.pem=${private_key}" \
    --from-file="public.pem=${public_key}" \
    --from-file="JWT_PASSPHRASE=${temp_dir}/passphrase"
