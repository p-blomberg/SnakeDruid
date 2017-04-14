# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|
  config.vm.define "php5" do |php5|
    php5.vm.box = "debian/contrib-jessie64"
    php5.vm.box_version = "8.7.0"

    php5.vm.provision "ansible" do |ansible|
      ansible.playbook = "ansible/playbook-php5.yml"
    end
  end

  config.vm.define "php7" do |php7|
    php7.vm.box = "remram/debian-9-amd64"
    php7.vm.box_version = "0.17.01.18"

    php7.vm.provision "ansible" do |ansible|
      ansible.playbook = "ansible/playbook-php7.yml"
    end
  end

  config.vm.provider "virtualbox" do |vb|
    vb.memory = "1024"
  end

end
