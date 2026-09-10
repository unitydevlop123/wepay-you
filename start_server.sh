#!/bin/bash
echo "Starting Unitywebsite PHP server..."
nix-shell -p php --run "php -S 0.0.0.0:5000"