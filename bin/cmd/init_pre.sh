CMD_DESCRIPTION="Initialize plugin for the first time."

if ! athena.argument.argument_exists "--php-version"; then
    athena.plugins.php.set_php_version "5.6"
else
    version="$(athena.argument.get_argument --php-version)"
    athena.argument.remove_argument "--php-version"

    if [[ -z "$version" ]]; then
        athena.fatal "--php-version must be set to one of the available versions: $(athena.plugins.php.get_supported_php_versions)"
    else
        athena.plugins.php.set_php_version "$version"
    fi
fi

composer_dir="${ATHENA_COMPOSER_DIR:-"$HOME/.composer"}"

if [[ ! -d "$composer_dir" ]]; then
	if ! mkdir "$composer_dir"; then
		athena.fatal "Failed to created $composer_dir directory"
	fi
fi

athena.color.print_info "Mounting $composer_dir at /root/.composer"
athena.docker.add_option -v "$composer_dir:/root/.composer"
