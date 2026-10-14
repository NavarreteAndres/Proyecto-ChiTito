"""Issue staging first; production only after staging succeeds. Run with sudo."""
import argparse,subprocess
from certificate_common import ACME,IP,certbot_args
parser=argparse.ArgumentParser();parser.add_argument('environment',choices=['staging','production']);args=parser.parse_args()
environment=args.environment
if environment=='production' and not (ACME/'staging/live/chitito-ip/fullchain.pem').exists():
    raise SystemExit('Staging certificate must be obtained first')
command=certbot_args(environment)+['certonly','--non-interactive','--agree-tos',
    '--register-unsafely-without-email','--preferred-profile','shortlived','--webroot',
    '--webroot-path','/var/www/acme','--ip-address',IP,'--cert-name','chitito-ip',
    '--preferred-challenges','http','--key-type','ecdsa']
if environment=='staging':command+=['--staging']
else:command+=['--deploy-hook','touch /var/lib/letsencrypt/reload-required']
subprocess.run(command,check=True)
subprocess.run(['openssl','x509','-in',str(ACME/environment/'live/chitito-ip/fullchain.pem'),'-noout','-issuer','-dates','-ext','subjectAltName'],check=True)
