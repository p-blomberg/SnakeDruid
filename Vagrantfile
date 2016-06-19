# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|
  config.vm.box = "debian/contrib-jessie64"
	config.vm.box_version = "8.5.0"
  config.vm.network "forwarded_port", guest: 80, host: 4398

  config.vm.provider "virtualbox" do |vb|
    vb.memory = "1024"
  end

	config.vm.provision "ansible" do |ansible|
		ansible.playbook = "ansible/playbook.yml"
	end
end