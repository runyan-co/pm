#!/usr/bin/env bash

export SOURCE="${BASH_SOURCE[0]}"

# If the current source is a symbolic link, we need to resolve it to an
# actual directory name. We'll use PHP to do this easier than we can
# do it in pure Bash. So, we'll call into PHP CLI here to resolve.
if [[ -L "$SOURCE" ]]; then
    DIR=$(php -r "echo dirname(realpath('$SOURCE'));")
else
    DIR="$( cd "$( dirname "$SOURCE" )" && pwd )"
fi

# If we are in the global Composer "bin" directory, we need to bump our
# current directory up two, so that we will correctly proxy into the
# Valet CLI script which is written in PHP. Will use PHP to do it.
if [ ! -f "$DIR/src/pm.php" ]; then
    DIR=$(php -r "echo realpath('$DIR/../runyan-co/pm');")
fi

php "$DIR/src/pm.php" "$@"
