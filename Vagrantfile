# -*- mode: ruby -*-
# vi: set ft=ruby :

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  config.vm.hostname = "emphloyer"
  config.vm.box = "ubuntu/precise64"
  config.vm.provision :shell, path: "vagrant-provision.sh"
end
