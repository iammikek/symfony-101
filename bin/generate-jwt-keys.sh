#!/usr/bin/env bash
set -euo pipefail

mkdir -p config/jwt

if [[ ! -f config/jwt/private.pem ]]; then
  openssl genpkey -out config/jwt/private.pem -algorithm rsa -pkeyopt rsa_keygen_bits:4096
  openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
  echo "Generated JWT keys in config/jwt/"
else
  echo "JWT keys already exist in config/jwt/"
fi
