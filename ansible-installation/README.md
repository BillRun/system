# Automate billing installation with ansible

## Description

The goal of this project it to automate the installation of the billing application using Ansible.



## Requirements - things need to be done before execution

### Setup hosts file

   ansible-installation/hosts file must contain the following group name "billing" containing the application server

### ssh-key for git 

Generate an ssh key which must have access to read from the git repository
```
ssh-keygen -f deploy_private_key
```
name : `deploy_private_key`<br>
ansible uses ssh to with access to the git project in order to clone the project to the remote server.
place the deploy_private_key into the root of the project

### connecting to mongo
if mongo require auth certificate or other argument for connecting, add variable name `mongo_conn`  as require, for example:
-e 'mongo_conn="-u root -p password --authenticationDatabase admin"'

### setting up the git repo
update the repo and the branch as require on `app-vars.yaml`
```yaml
git:
  repo: "git@git.bill.run:sdoc/mtn-cy-billrun-plugin.git"
  branch: master
```

### setting up the shared folder

Open the file  `app-vars.yaml` and change the default of `shared_path`

## Run ansible

```
ansible-playbook app-install.yaml -i hosts -u <remote_user_with_sudo_privileges>

