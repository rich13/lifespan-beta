#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
LOG_DIR="${REPO_ROOT}/storage/logs"
LOG_FILE="${LOG_DIR}/pest-latest.log"
TIMESTAMP="$(date '+%Y-%m-%d %H:%M:%S')"

mkdir -p "${LOG_DIR}"

{
  echo "=========================================="
  echo "[${TIMESTAMP}] Starting Pest test run via run-pest.sh"
} | tee -a "${LOG_FILE}"

pushd "${REPO_ROOT}" > /dev/null

./scripts/run-pest.sh 2>&1 | tee -a "${LOG_FILE}"
EXIT_CODE=${PIPESTATUS[0]}

popd > /dev/null

{
  echo "[${TIMESTAMP}] Completed with exit code ${EXIT_CODE}"
  echo
} | tee -a "${LOG_FILE}"

exit ${EXIT_CODE}







