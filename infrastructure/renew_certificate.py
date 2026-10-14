"""Renew, reload gracefully and verify TLS against disk; root-owned systemd tool."""
import argparse,fcntl,subprocess,time
from certificate_common import ACME,ROOT,certbot_args,disk_fingerprint,served_fingerprint
parser=argparse.ArgumentParser();parser.add_argument('--dry-run',action='store_true');parser.add_argument('--force-renewal',action='store_true');args=parser.parse_args()
if args.dry_run and args.force_renewal:raise SystemExit('Choose dry-run or force-renewal')
with open('/run/lock/chitito-cert-renew.lock','w') as lock:
    fcntl.flock(lock,fcntl.LOCK_EX)
    before=disk_fingerprint()
    command=certbot_args()+['renew','--non-interactive','--cert-name','chitito-ip',
        '--no-random-sleep-on-renew','--deploy-hook','touch /var/lib/letsencrypt/reload-required']
    if args.dry_run:command+=['--dry-run','--run-deploy-hooks']
    if args.force_renewal:command+=['--force-renewal']
    result=subprocess.run(command)
    after=disk_fingerprint();marker=ACME/'production-work/reload-required'
    try:current=served_fingerprint()
    except (OSError,ValueError):current=None
    reload_needed=marker.exists() or current!=after
    if reload_needed:
        subprocess.run(['docker','compose','exec','-T','web','nginx','-t'],cwd=ROOT,check=True)
        subprocess.run(['docker','compose','exec','-T','web','nginx','-s','reload'],cwd=ROOT,check=True)
    for _ in range(15):
        try:
            if served_fingerprint()==after:break
        except (OSError,ValueError):pass
        time.sleep(1)
    else:raise SystemExit('Nginx certificate mismatch or TLS verification failure')
    if marker.exists():marker.unlink()
    subprocess.run(['openssl','x509','-in',str(ACME/'production/live/chitito-ip/fullchain.pem'),'-checkend','86400','-noout'],check=True)
    print('Public TLS verified; certificate matches disk; changed='+str(before!=after)+'; nginx_reloaded='+str(reload_needed))
    raise SystemExit(result.returncode)
