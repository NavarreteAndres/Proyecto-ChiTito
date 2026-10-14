"""Install only ChiTito's root-owned renewal tooling and timer."""
import os,pathlib,shutil,subprocess
from certificate_common import ROOT
target=pathlib.Path('/usr/local/lib/chitito');target.mkdir(parents=True,exist_ok=True)
for name in ['certificate_common.py','renew_certificate.py']:
    shutil.copyfile(ROOT/'infrastructure'/name,target/name);os.chmod(target/name,0o644);os.chown(target/name,0,0)
for name in ['chitito-cert-renew.service','chitito-cert-renew.timer']:
    dest=pathlib.Path('/etc/systemd/system')/name
    if dest.exists() and dest.read_bytes()!=(ROOT/'infrastructure'/name).read_bytes():
        raise SystemExit('Unexpected existing unit; review before overwrite')
    shutil.copyfile(ROOT/'infrastructure'/name,dest);os.chmod(dest,0o644)
subprocess.run(['systemctl','daemon-reload'],check=True)
subprocess.run(['systemctl','enable','--now','chitito-cert-renew.timer'],check=True)
subprocess.run(['systemctl','start','chitito-cert-renew.service'],check=True)
subprocess.run(['systemctl','list-timers','chitito-cert-renew.timer','--no-pager'],check=True)
