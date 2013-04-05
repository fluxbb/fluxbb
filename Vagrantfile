# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant::Config.run do |config|

  ## Ubuntu 12.04 LTS (32-bit)
  config.vm.box = "precise32"
  config.vm.box_url = "http://files.vagrantup.com/precise32.box"
  config.vm.host_name = "fluxbb-vagrant-dev"

  # Set the default project share
  config.vm.share_folder "vagrant-web", "/var/www/", ".", :create => true

  # Forward a port from the guest to the host
  # 2008 is when FluxBB was created
  config.vm.forward_port 80, 2008

  # Set the Timezone to something useful
  config.vm.provision :shell, :inline => "echo \"Europe/London\" | sudo tee /etc/timezone && dpkg-reconfigure --frontend noninteractive tzdata"

  # Install all needed software
  config.vm.provision :shell, :path => "setup_vagrant.sh"

end