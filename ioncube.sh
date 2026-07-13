#!/bin/bash
# Installs the ionCube loader for the PHP version currently active on the system.
# ionCube publishes loaders up to PHP 8.3. If the system PHP is newer than what
# ionCube supports, the one encoded controller (ProController.php) will not run;
# everything else in the panel works regardless.

uname=$(uname -i)
if [[ $uname == x86_64 ]]; then
  wget -4 https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
  sudo tar xzf ioncube_loaders_lin_x86-64.tar.gz -C /usr/local
  sudo rm -rf ioncube_loaders_lin_x86-64.tar.gz
fi
if [[ $uname == aarch64 ]]; then
  wget -4 https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_aarch64.tar.gz
  sudo tar xzf ioncube_loaders_lin_aarch64.tar.gz -C /usr/local
  sudo rm -rf ioncube_loaders_lin_aarch64.tar.gz
fi

# Detect active PHP minor version, e.g. "8.1" / "8.3"
PHPVERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
LOADER="/usr/local/ioncube/ioncube_loader_lin_${PHPVERSION}.so"

if [ ! -f "$LOADER" ]; then
  echo "WARNING: ionCube loader for PHP ${PHPVERSION} not found ($LOADER)."
  echo "The encoded controller will be skipped; the rest of the panel still works."
else
  # Write loader ini for both fpm and cli of the active version
  for SAPI in fpm cli; do
    CONF_DIR="/etc/php/${PHPVERSION}/${SAPI}/conf.d"
    if [ -d "$CONF_DIR" ]; then
      echo "zend_extension = ${LOADER}" | sudo tee "${CONF_DIR}/00-ioncube.ini" >/dev/null
    fi
  done

  # Also ensure it is present in the fpm php.ini
  PHP_INI_PATH="/etc/php/${PHPVERSION}/fpm/php.ini"
  if [ -f "$PHP_INI_PATH" ]; then
    if grep -q "^zend_extension" "$PHP_INI_PATH"; then
      sudo sed -i "s@^zend_extension.*@zend_extension = ${LOADER}@" "$PHP_INI_PATH"
    else
      echo "zend_extension = ${LOADER}" | sudo tee -a "$PHP_INI_PATH" >/dev/null
    fi
  fi
fi

sudo systemctl restart "php${PHPVERSION}-fpm" 2>/dev/null || true
sudo systemctl restart nginx 2>/dev/null || true
