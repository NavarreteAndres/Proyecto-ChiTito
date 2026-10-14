"""Certificate tooling. No credentials in logs or command arguments."""
import hashlib,ipaddress,os,pathlib,socket,ssl,subprocess
ROOT=pathlib.Path(os.environ.get('CHITITO_PROJECT_DIR',str(pathlib.Path(__file__).resolve().parents[1]))).resolve()
ACME=ROOT/'infrastructure/private/acme'
IP=os.environ.get('PUBLIC_IP')
if not IP and (ROOT/'.env').is_file():
    with (ROOT/'.env').open() as config:
        for line in config:
            if line.strip().startswith('PUBLIC_IP='):
                IP=line.strip().split('=',1)[1].strip().strip('"\x27');break
if not IP:
    raise SystemExit('Configure PUBLIC_IP in the environment or project .env')
try:IP=str(ipaddress.ip_address(IP))
except ValueError:raise SystemExit('PUBLIC_IP must be a valid IP address') from None
IMAGE='certbot/certbot@sha256:f70ad0adbb7e117f0fe42a63c553f28ea451edabc0148757b6efcd9735acaa20'
def certbot_args(environment='production'):
    return ['docker','run','--rm','--name','chitito-certbot-'+environment,
            '--security-opt','no-new-privileges:true',
            '-v',str(ACME/environment)+':/etc/letsencrypt',
            '-v',str(ACME/(environment+'-work'))+':/var/lib/letsencrypt',
            '-v',str(ACME/(environment+'-logs'))+':/var/log/letsencrypt',
            '-v',str(ACME/'webroot')+':/var/www/acme',IMAGE]
def disk_fingerprint():
    der=subprocess.check_output(['openssl','x509','-in',str(ACME/'production/live/chitito-ip/fullchain.pem'),'-outform','DER'])
    return hashlib.sha256(der).hexdigest()
def served_fingerprint():
    context=ssl.create_default_context()
    with socket.create_connection(('127.0.0.1',443),timeout=15) as sock:
        with context.wrap_socket(sock,server_hostname=IP) as tls:
            return hashlib.sha256(tls.getpeercert(binary_form=True)).hexdigest()
