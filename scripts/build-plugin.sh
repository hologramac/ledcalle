#!/bin/sh
# Genera ledcalle.zip listo para subir a WordPress (Plugins > Añadir nuevo > Subir plugin)
set -e
cd "$(dirname "$0")/../plugin"
rm -f ../ledcalle.zip
zip -r ../ledcalle.zip ledcalle -x "*.DS_Store"
echo "Creado: ledcalle.zip"
