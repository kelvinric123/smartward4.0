#!/bin/bash
# update_temp_mp5sc.sh — deploy the temperature dual-source fix to this Pi.
#
# Single-file updater: the 3 changed files (listener/main.py,
# listener/src/config.py, listener/src/reliable_ipv_data_source.py) are
# embedded below as a base64 tarball. Copy just this script to the Pi and run:
#
#   bash update_temp_mp5sc.sh              # install + restart + follow logs
#   bash update_temp_mp5sc.sh --no-follow  # install + restart, no log tail
#   bash update_temp_mp5sc.sh --rollback   # restore the previous files
#
# It re-executes itself with sudo if needed, backs up the current files to
# *.bak, syntax-checks before restarting, and auto-rolls-back if the service
# fails to start.

set -u

APP_DIR="/opt/mp5sc"
LISTENER_DIR="${APP_DIR}/listener"
ENV_FILE="/var/lib/mp5sc/mp5sc.env"
SERVICE="mp5sc"
PY_BIN="${APP_DIR}/venv/bin/python"
[[ -x "$PY_BIN" ]] || PY_BIN="$(command -v python3)"

say()  { printf '\033[1;36m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m OK \033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mFAIL\033[0m %s\n' "$*"; }

[[ $EUID -eq 0 ]] || exec sudo bash "$0" "$@"

FILES=("main.py" "src/config.py" "src/reliable_ipv_data_source.py")

restore_backups() {
  local restored=0
  for f in "${FILES[@]}"; do
    if [[ -f "${LISTENER_DIR}/${f}.bak" ]]; then
      cp "${LISTENER_DIR}/${f}.bak" "${LISTENER_DIR}/${f}" && restored=$((restored + 1))
    fi
  done
  echo "$restored"
}

if [[ "${1:-}" == "--rollback" ]]; then
  say "Rolling back to *.bak files"
  n=$(restore_backups)
  [[ "$n" -eq ${#FILES[@]} ]] || { fail "only ${n}/${#FILES[@]} backups found"; exit 1; }
  systemctl restart "$SERVICE"
  sleep 3
  if systemctl is-active --quiet "$SERVICE"; then
    ok "rolled back, service running"
  else
    fail "service not running after rollback - check: journalctl -u $SERVICE -n 30"
    exit 1
  fi
  exit 0
fi

[[ -d "$LISTENER_DIR" ]] || { fail "$LISTENER_DIR not found - is mp5sc installed on this Pi?"; exit 1; }

# 1. Extract the embedded payload.
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
PAYLOAD_LINE=$(awk '/^__PAYLOAD_BELOW__$/{print NR + 1; exit}' "$0")
if ! tail -n +"$PAYLOAD_LINE" "$0" | base64 -d | tar xz -C "$TMP"; then
  fail "could not extract embedded files (script corrupted in transfer?)"
  exit 1
fi
for f in main.py config.py reliable_ipv_data_source.py; do
  [[ -s "$TMP/$f" ]] || { fail "missing $f in payload"; exit 1; }
done
ok "embedded files extracted"

# 2. Backup current files (only once, so a re-run never clobbers the
#    original pre-update backup).
for f in "${FILES[@]}"; do
  if [[ -f "${LISTENER_DIR}/${f}" && ! -f "${LISTENER_DIR}/${f}.bak" ]]; then
    cp "${LISTENER_DIR}/${f}" "${LISTENER_DIR}/${f}.bak"
  fi
done
ok "current files backed up to *.bak"

# 3. Install.
install -m 644 "$TMP/main.py"                     "${LISTENER_DIR}/main.py"
install -m 644 "$TMP/config.py"                   "${LISTENER_DIR}/src/config.py"
install -m 644 "$TMP/reliable_ipv_data_source.py" "${LISTENER_DIR}/src/reliable_ipv_data_source.py"
ok "3 files installed into ${LISTENER_DIR}"

# 4. Syntax check before touching the service; restore on failure.
if ! "$PY_BIN" -m py_compile \
    "${LISTENER_DIR}/main.py" \
    "${LISTENER_DIR}/src/config.py" \
    "${LISTENER_DIR}/src/reliable_ipv_data_source.py"; then
  fail "syntax check failed - restoring backups (service untouched)"
  restore_backups >/dev/null
  exit 1
fi
ok "syntax check passed"

# 5. DEBUG_MODE=true so the monitor's temperature label IDs get logged
#    during the test. Turn it off after testing:
#      sudo sed -i 's/^DEBUG_MODE=.*/DEBUG_MODE=false/' /var/lib/mp5sc/mp5sc.env
if [[ -f "$ENV_FILE" ]]; then
  if grep -q '^DEBUG_MODE=' "$ENV_FILE"; then
    sed -i 's/^DEBUG_MODE=.*/DEBUG_MODE=true/' "$ENV_FILE"
  else
    echo 'DEBUG_MODE=true' >> "$ENV_FILE"
  fi
  ok "DEBUG_MODE=true set in $ENV_FILE (for label-ID discovery; disable after test)"
else
  fail "env file $ENV_FILE not found - skipping DEBUG_MODE"
fi

# 6. Restart and verify; auto-rollback if the service will not start.
say "Restarting $SERVICE"
systemctl restart "$SERVICE"
sleep 5
if systemctl is-active --quiet "$SERVICE"; then
  ok "service is running"
else
  fail "service failed to start - last log lines:"
  journalctl -u "$SERVICE" -n 30 --no-pager || true
  say "Restoring backups and restarting"
  restore_backups >/dev/null
  systemctl restart "$SERVICE"
  exit 1
fi

echo
say "Update done. Test flow (watch the log lines):"
echo "  1. probe only         -> 'Temperature source: label <id> mapped as probe source'"
echo "                           then BP capture shows Temp=37.x/probe [queued]"
echo "  2. monitor press only -> Temp=37.x/monitor"
echo "  3. both, same value   -> Temp=37.x/monitor+probe"
echo "  4. both, different    -> WARNING logged, monitor value is the one sent"
echo
echo "  If the log says: unmapped label <id> carries a temperature-shaped value"
echo "  pin the probe label with:"
echo "    echo 'TEMP_SECONDARY_IDS=<id>' | sudo tee -a $ENV_FILE && sudo systemctl restart $SERVICE"
echo
echo "  Rollback any time:  sudo bash $0 --rollback"
echo

if [[ "${1:-}" != "--no-follow" ]]; then
  say "Following logs (Ctrl-C stops watching; the service keeps running)"
  exec journalctl -u "$SERVICE" -f
fi
exit 0
__PAYLOAD_BELOW__
H4sIAAAAAAAAA9Q8/XPbNpb92X8Flp6MyVRWJKdJWzfOXb66zV6T+Ox0d3ZyGQ4kQhIbiuTyQ7bXq//93nsASJAAZfl6velxMrFEAA/Aw/t+D1rzOB3nN1/9ns8Enm+fPPlq8nQy/fbJlP5OJlN6P5k8fTI9+ear6ZOTxyffTE4eY/t0cvL06Vds8ruuSj11WfGCMfgriun/xYR/rOfwT4/qsng0i9NH+U21ytKDQ3b88JjNsyhOl6esrhbH3+Gbg4N4nWdFxVa8XCXxTH/9tYQx6nNW6k/lTfOxWhWCI7DmRbwWB4siW7OIVwK/MdWivx+o5qwS6UY3JhmPQvnq4ODVLxcXb95/DF+/vWBnMO8459VqHMVFytfCD8NFnIgwDA4uL171+vyaxalvDB8xryzmXgD7WzDdPc0qFqe4Cxp0esDg0d/GcQq0UvmTke4fqAXzPA7nSSzSSi/6RR6/oheywzxLF/Gys6NSVBUgp5QdVoIX1UzwBsBP+sWlSCNRyF6FSGI+gx3G+SYEpPGwzOpi3uDxQrW/zTevofWSGuXQssoKvmx6fqirWXZ9KV8eHBwYSPZ3YGwM7V4A2z6YJ7ws2c/ZcimKd/F1nEpcRWIB21v6pUgWI7YWZQnwRywRG5GceW/f//jBC2RPfPDMgQ3XOZyTpoFxml35wbisigV+9b0Hfz9+sD5+ELEHP50+eHf64BLOTAPAU4exS1HxqirUrF4kNvFchNjowdd3PIVFFMawvIjTyl94n26bFWw/s0+3tEz6hGPhw63awdZr96xQR8fiN0Q+/kifRiZKghYnYRincRWGaon68Ef6XEYGDRkIKuscJgnGzfCIi3WWnn0satFuB2GONUhAR0Na3Q6KAs70nN1mg4bPjMV0OxmYhV6eRILX7VPUaQqzQzuustsGCKzC5TzkOMnkoEHPPFvndSXCQlTFTRiJhN8oTMk386xOq1NgzSpgx8/xb4sj6k27NrAwluNmvBTAacB+UckeMv+EPXzI1vzaN8CyYzYdsUnQ4hMa6yJlayB/Aj5ywgYwGnQgd/LvQElVPF8LkKdRe/SAat3RnwORVCICBJzCMRS0mwUwn7EfgN1+wUeNMXkEhubEHS08IPRdnGLuC/bvN6Dqak4cB2hQwIJxlVU8adYcIHYaQOJ6LvKK+X/lSS3eFEVWjNjHm1x+DE5dExoHveJpBPJrweOkLoQ6Y+C6FNavXobEZiO2qqo8RIzW8CUCDo8TA7znea/FPI6EpBC2KaEPj44TOCRRMDz4iGWpBsok78LsShbDO/jI83IMkNqTz67CGDFNS/rkxZH32SSLhmbOkAZ91cto8D4H7Gs2bcYohkPy6dCBGtkeHww8MEia4yJ+5EkpjOk5qFx4/T5L27egvDp4Y2fAlyteRF73KCISTg0MZE3AuBenG57AjnN+gyqA+T99/HjObg3cb4MWPaBeXNOV2aLyThk7ZN9MvgFQFUkR1KULwEnUWQcAQJw877EUMhPiBwCEwHcgC5Bfn066m9ixEWtW+L+AaW7vnGe7vnODvK5WPXxCP5MenPsBSlznVRni8L03gp3h7a9ijjzPF0jNt8ZUW6bhmssuBeG/KnhadsT2HjhvBoUrMBgQ74+fTvbHPOAa0LYRBVoeu7Hem2m78g5MQsYJutOaigugFF9C7ONLNtVr6YmKnhhRkqMDlvQ8KrA3FxcfLjybicAG9CUXjdTpB4Rk5v3txcX7t+//7NnLRLNnAUKpFUK3ksuP6A8s+OjzFkyLzkzbz6d4uriNLXySi916ymIKuifcY2hD7bn0p0EzwR1Ipa4GVpuBI6YUoFzYLkw75kCMWFS0IKuBxgJ974UitaQeG8AZ3dLqtqWJOWvC5si6TUGrlNb8Zga4q9PwVw5mViYNSUPZgIIETJO6JK0bmFSLjceWefPMEl5SGe6whwDQsBFQNBaOOjhc7hqceDDZeToXNqItmwUwHGcpqCDNfzauhsYg14HDcXPnEOT0aBYCKe3bdT3rn4v+pCyNN/QHVsF4ie8cEkKy3l/k4TGBlgiQBPRFTlJM7jKFzGPkKXDAeINWDZg8wcAsNnXpWfO6WAJBI3bPbuUXQvWWxFrzCr9sf2CeDQhN66qBoL51QOh3BIPlwEaAyPa1erH1xiCE17zyHz4sggHcovr65PVGep/33fXCe/Hzi4t3p0wv+rZAHu5AQ1b+5f0lOI9M+Uh+GbAqA7sXBBdqjYKV8T8FWmEOfCw8HykEKenMoU4UkW1HaM4CEQ30Wc+2aNmirwx+aVm55IOkj0HpAIzWlwcG0UkXCPdUIB6uYtDe/6hFLdjrl6daDyqWhfWgY701aPFqBWKw4zd1j8CSA830ttAKrI4kVcu+5FiIar4Ko1qEst3vYg4oLgLHCfvg8dhQSeZVCri9Olo2OSmJELkLOAqtAjjNBo0PGMjQFzxHmySQuTcUZUl3Tn/IzkVxDPTB4jJLOAqPU8ZZnsVousj3prsgQOvhlhKxqAYASrpNElatBJxaBqdGOBq79+86Nv1oW/uMYmhj/Nw4BKotxBZ0CVzj95KL5jNgRRk+DkjJeVYUNYBVKwDBiX7GiGkzCFxOH2YJ3GtqprGtIA0Y0e5Q93cIavMZpgx8si/7uJCaGdoIh6TJTUwub7xMS1vc9U5upN1DvQ3DSTQf906AfbIv9zksVAHmYe1xAoM98CGhVTlNL+Qw7Undqt2Ol6Lyj9RbML4icTRiR0fB1pbZuzdu27DW2nvhgf0DAzZJGCJo2l3P3vxji/kky/K97IveCnYKwUbTwKnnTlXThtRkSECHIv+KRHsJNPtzXIKp9jvFI0HFZqRhwjhHhDchwDPvnWzwmtdxdIZi4w8cwmz3Ap3aL7vinMY3ZzeKGDWfd0ZDHea/5iwdVPV29KGZ+j3axnAODLSkeBLFV21AFO0JKdsQFrwSO7tl1zdLke7sgpGInR0KUeb2TIfsFc8rjMs1AfiSlVLFCi3ns7pkJJZLxqEnKmheVXy+AjsL1DFnL8/H3XlXhXPnch/OJly/s6FZd7/1kL2L00dgXrJsVgIHCxlpusw/nFB48fyCRXWBR46WwrwuCqTHudruVZxG2RWwWWYAvEIbGOwSQakZzgo8ROaL8XLMvO+fHH//1AvA5AEWB6shA1cF7OZ0iXYj4C0RPSSUeXaCsa1+pNBohMW7GgF9Q+OwyR51yP62iucrXBNutrrKNEcxRKwA/OGmZZKq7CCEHC3Y9tqERpkq31s3UsXLi2yGSRyAOMvAtL5aAUECnBvGl4UQwdhxnConNsBK1AWE+lC7VCyhPAwrUQFkIZBz4IDKvqj2PO8S3QA4nwX0WxFNPAJ6kOcpj575MqTHNUkEnQB0/wTvcWiOc+otW9o4c+QgJfdBaPXlNQbWC9Byc8loNi8Cm8IIbSYclfIoS5aCti5YEUftAnmSAR9kdGLQfl0Zo16eszLlebnKKhsDfyRZ1ZEsljzpSZHdsuO30egh+2gyVZKBa4eBV/SGEME5L0AgsauC59CL+XRK8EGzZMJnIglGBjw4SuKlFQcoIFJnmCcBfKOAXQmUzlnWchhFaDfoOqEoRrLpRyQ3YxoeGsxf+oElWFsOMoyfOseUlGI/bZYoIw+IMKV4+BnZP2YGue9o9Y17BVZpZwN6q7wd89wzCAWY6Vp4kZjVy3ANtvJuq5LmVVtXIVHDsHz95uUvf/YMHIUkVxRylPxAZd9lXzQ0KKLl4mESu3CEhZijGBZpVi9XpE5JtTaKtZMVk2k8Y0L2nE1I2/lGUBQziMaa2LN+Tlb5WKjjU1GWOhnW7m9Wx0kUKr9DbbO1bkatsTBiM+Chm5L+RjEv237SMDWrDMDYgCVhJFObEu8/fCQLEnCDZP6D0ijQ0hgYsxviqRfnb5kyKXllcg7GsKmERfKQivkAfURx+UXJQsQ7B70NayvLq6yIypaZ2iDAbYdCPNPR8k7N7Xf7rQUvFaqhW4OZ3YUTPRhLILsrfhM2fuxpN52g3/v7HcIQ+BZwQwptUztma0oa8zi7PKQw98kzu3ifAZPmi05iSa6zIVr19Rl73E9zNbBn4OkBJQK7IZKxe5Ul8ZymwcyvBBH0JkEkmLPQ92fsZN9psL81D2HWJGZLI2dpckOKVylcWIxmRApIKv3QMJ4yRcYGUNN8BZAZACgKyQLrvrlrmrcds7ajKmTOuxVYUoUGNMkEkLJDz+86mLwGBUDdGhwNQwqcUlpZTIYeo0WZ9tOgjhtYCwJs1lNg+tk3pnLEzJww+PUQDH4dBDuR25gkwwhWFhJI5ekgdtHSpDUY81sgRmzqRmxjtzpR21ix+yFXA3OhRLftQqyebnj8nUhVZp1E6X+5FXkj0agzrCmcN4qvb3taClGO4dfhfOA8DENqx7Fgr86huDZjGqXDRNKawM92kAn2iqFXVtzsZsUGnFVcpWDZJWCNzrmP+je0fcExV+v9y5P1i50NfLLPUFUNGqEcsJR7gaLAThq1y0KFG+e+o8//SCfrRRlKZncP2n6vSxuOtvCuqnjH5YpPfcDVWKRoafgeFfx6QTBeiesoBuO8Mq3zQhApha11klbgz7VupHECnWCVK4bV9FzUSaLjXu6A2LChr3tRao/CbxioDs3XvYzYHIg+Jm9ArgxwaPb+NPn8afo50OdpGiFts6oC8ZxLAT2uN9OHPd0Ne3on7CHAJ7sBnwwCbrGhQC+8295GtuzWnH7r6Wn60t/ErEukG/Rgdh0GY1t9fXLp9h101/o0U5atYXjIXshiwGItoibtIUOo0g8utQelw5CoyzDEI2NZKMcTM36FPF6gR4AuFKv4F4FVpNFxCVYY+FawZlR8M3IzinhWVzKSSSnFAi03EPaYEnQZwoA9nN74+ic3d7ld052d8NmdO1p45x38RCq13QO7xXrWW+P7DxKRaC92UOnIw+MzUKuDj63qjXCCGdlCYWSHBIaC5GZRj4n2ltacaKaWNll2n+PYdQjmxO5UmRE9aE5EymIVRsAAQgNjy/y3r087JxI4MlXDmTnHdG9fGzMZgD0H1l0JjmZxd3QncWEoDduCaC2DFmY/noQBslBV9UhlJYNrhr5SIek2hEgF2b7s9+kJCVircycs2RnwVA4YCNsp4xdzczLcDe4ZhvFKLG+vZGSmzBJKK2AzRr0NUDqWp0PqYGyrjRqmom+Exe1qcrUBM9jZWf9UqhSkRXAX1dsA3NrpiVQkk7609d+mkbhWteBtXXhw98STFk3DNXaH4JSesi9C5CQpExhaVhiHo6AnGHk6m4DysSr4/IuUqDLertxX2zdtrPC8kOFIcl3ARnccsWW5t2PAobFdA6ozt8GAiPju8XffPZ186yq0Goh523DssTo8bZYQmo0yQ+BYke0Tk9NG54z3H4zGkWO8g+WbDMQds0lH0JiNX5v+7u7ZZMDiFBNgAo5c8GqNwd7hMyaPz3nKrVs8POLOM1ZA9j3fRtJ0x9tjzEyD82yNVFFvLS6n3HG2unnUG+8qGW7TUDvnGjxZ3Tw8FwBCr9QWFeSNdlDetu2LdVPwWFDskb0sDqJ/mL6GAv7DVpV3AY6qSp25qh9/uji7tVlgO2IfTtoGiT946YJwYUBoNuOskJaJhsEKSIeGcfl9nuddSN3Vyf4qLSe07joqKVHsSBCPWwR/bPuzvJ6BflxhAtkYIwtI1xlmqcCgo5wkZbhOaTLCbKsF0RR/1Br7KiWpZ4gr3A/zobVECzeL50LGSGxQ82yN1myzK3NNlK1mvsxraDhjdl6ITZzVZXIjQ7UwrHWBacor0GZ1iRcoRtJ9IEjHMrArC3qoj0pawCfv+NjTK2yAKTflCs20rK5kNWraxTUQM08SA9UXdSLKEVoSWQEoNel3OpZZdwxQUwoE5hP/qHmCZj6lQMBk8UWMyUKmr6JKvczT8kp0yo9PesDA/WFRvFgIqgcAgOaJSIUOKrw0IDwe99pTMPv0SIn7Jr+Fr/T9Y4NAW7ybtaQYPIgKHqcy86rKYodqwBfeR4t2T1U53dYLTEOV5i+N8IRxEqFuNkwdZbPsmzjTw/IiXvMCb4NomFS056n3npmCxZGuvk2L1xHHGjT5nvLzJ48vMeT3XC2352M3a+nfU2vnljFh9W03OHPBbU0DPlFcUg1Iad2U6y27AWG5YnxW+s2miHRgHcfm0tRLRzJTBodxAWh6hFWWwLmmc4cfBXKgxKh5k/NXU4LUVuT8tSxz6Yx0+2SHgAQqEZK3sKqMRVisYVD7Dw4+UvxgnkcLEFvwRxDaWMhI5X3KNVY44+gEiwhlodMiS5Ls6rjO7crn7kZHnROy9uyNuveD6eqd6tVzHO7EX+8C38CBW3Cafk2hkQGnj311Y8TgJGd1iZykIRzDYn2VpRtBv4sgdI0GqEIsZ5yRy5JJV5AqO4/wbwZv8R6rSjEboMQ1+IuoE9qU3pLs67SbQienCfRBVnzB3nWqQh+uqql+NASz+WovxJ+OIS0i5Q8NtF3iZSqlI4UVFZwjwsnR5+2/buUAo4IYT60Z1Imk6FqYIdvOqJVpIPT5vCHE+4e9TFFvbeSVNAM0s/lkf7Td6Ct0C1z3fSR4qbVksZkMqmnRY0yi4RqNDWg4pi6vAytHvzW65pY9AwpwCCv6kIfR4tl38GTVHRVTULGO+E1X8axavrtv5cEI68IMKm5qMGZrrpBqnBIJY6dPHt6u8HTUnX7zoic1+uS28P6TykeaK10S0JFxk4quPjYN9A1fez048qKY6oVfsFOWRAKwAVtpmtQrNYG8mg5d+64IAJy14GZ0IShcz6Dnu5c9KiKEtK8Ch6B0lFY2XtUeN60w8I5ibHNC8TKsdyeNdNtL12293uTd0jLvlazywiL58RjUkKwpM2/fb2Blzt9S8XtT9X4ZIjBhKJ8wTheZZdC17mI7rRWpQxiUbVC1bL7DJ3dvDSUA6xbYd5Cp6/Z1wRvWtWGqUvpZkYG/+97B/FFe65W3pfCXfSitqkqo7rgz4djQojks9VMOZn2cOjcTRF9JS/1M1jMVa5nFmNSWznLdYv+4hjeZjuHf9PvvJ2wyOaV/uPgH0fjBevzg767f2dCJUrqkLIpujNM64ruu/9EgUnCGD2FeVbJj+f0FfH1m/ByFfuKF1e+5/QsqsoO+qOJOEOCi+slhR+51L/ToxxnZb4tr3ZloSvtYoNDB1Afs6wj3d5QUfcgm48lkMj15ErBHoBdQlTZdvnd3cSGynQLslh7B7UiqOPITKtTuHGNTcjOvvWsr5TBfifkX4EssS7xhOZjw8sIjBRh0TX0boHEALATeo9wYFzYYjzbo8JR4tbLMs+qYZmHapJFRizlPHdB4USAsjE9gnKJa8coAvM42MHREzltckUWjqpPBJylN89WFUTNM5SYKFO/qnL/Bc7Y6XLUdphNnDzh3398AxVxZv5aDBYAyeOR3RQyQxyZwU4SqSOxlYibd1FNvABUX9nM3vdxTF+vntbq/ShyESZSYymIrrB5crjAzvciKNjstq/ximxwkOHXEI/NqSlXUZaWrA+EY4iyK55rrrTJB87mnIPlflBHqPJEfduVm9dNq0csvcZ7jZl+e60rIpmAX06RIrXitftRaicN3N/vqaDPYc/BeIfL1HZer8dl9jfaQvdVE8Wtdtvn+s+fSP4W9wv98PYuXVIPqgyhPImRO00ftQsxVALS9/hGMGd0yAfYGVsXwIQVxCyye5rIY4lh1HqS+eVZEP6DsoTKMqA2UxkDmM9HWwJoXUOb1YuEmQGTprv87XDgQ6NK5Bveak0K5Gi0O9iCjwR74LOgyjiayW6MqbPvo1qwA2562zFynGiXDV3QlcP/Z7V7bAMcAKHkuelUywYCjqZ8dDqd+/j8wxHkMaq3OdVi+ITRZ4NMRnfKK22LBFnFKtrSb1u6nr/Bp7wvQ2O5NCVMYbhx3IxoBOXAxe2PUW6gST+tqgPuKu/xJTBH1XWaRkg/v6+Gj9u68WW6JaxpZ5ZZulFUyc+cR4Ij87WZ26WpHdZ7EcwqELVNMlLjJ/27OW3itRJdRz7akplv1A2w3zGMLbxfD7hx4fnHWu3/fqTXHG/j/dhRsj4c78WvdaedEmEfvTaVTwgOT6CzuXuDRDu2BN+gdQRwf7/wpAcDh0aMj9rUddqRSqgG46te6jo7w16SIcraf3XPssLbvlD2H7L246t2BIGpptI2+jD9Mh67rcFanweDYPX50YEAWKgf/P8TNLONF9BY74M91DHj43uWqrmCbeFsZFlJW8qoU5g9+Q9RA/3zB/r+v4IwVvOkO7MUHQCLzJHFdF+z/vILZAc3SFU/uEYN5HZdzIwzjiFK02Gw2jj/9kFPkpYmG3fvXIN7VSRX/d3tX1tzGkaTf9Ss6wAcBOyQIgodJerGxENE0EcNrANCyV+FoN4GG2GNcgwYlcTX875OZdWUdDVJee/YFHQqK7K7KurMysyq/7BBjlZC31W8Cf3DLeRGOwUGDVTi7DhWN2WBhDjuzU0xqCZDkmO8sWAcNrGwT8JAk6HZAywbrtXJsW9XbdotyCBp85lZk7xsOUPM3FSJelDjTqK7RRSfZDA2S9rVp4nPWSatreZVTDIfx63PZTDLgFGLqodVR5ZTTBK2dr0b9wM15QVDaVh3W2t61UbcVADbxF5Du48An2d/+F9b31kcjfbSgnS4H0IAnJK+EvkIPGH/iwFCotgmDrusTrfvnQ75AVxr1d6kZHLiubQKXl32/5gv7WAWZiD+QbKBYp9tVoU2VC2NSRVYJXjWUdWJigW4g3xzcq+aPq9Zeo6w/YNdaVHHi0fQKdwcxTqc7xCGA6YWn2VC6gBfm6hL+xXoCp0a+oGUC8m++SNLRCB0zK78QzZG8KJLTtdtxJKQOnq5mVpe6diXowUytOu36LXtClEGz32MRYsXwvDuqVmFYVDa0OFIWMWl5N5V2bj8sIoVZ4zfEZUiQmGxxCwWQz+pYck7osBCDHCRK09jskpXU7M+gb9ReCcFnzoWubg/7Z3g6NPUA39mE+dvrwfnYVuKtW32VVXHnwAjpbzq3l11zeZCgEhzr4GCZxortXV866k+B7nuQ29BRnQtgE6+ASfYxHT4lhtGVSmNOGeP8C6yvq5vr7uCml3RvXaRFj/CzVRH3JNm05XquJqmU6A0y9wuwnYafoJxOeIbW0c7vOmd5aUhCeAesfO/8RO4HAm1RC94leInr8AJV+T5ynURylIztBVw4iwkq9veyeQpYLGTEuwnoDaDyPRMygvirWoR8SvD5fUiF9iyEwn1kjJfNqXK3ckQg44z8ymUR7klfPFpDEKp7Se8izezKdU41l0MXHHSvTnjj1k7XF6pGafxuWnNq9X9vukdb7HzbZgeH/QV/d7fMHBR8H5nXGW8tYORFkuLZmnuL02uRo4Eq8UFpZNEjKPILUuYmTwhEXcjt5nXT8FXizGt6mveQbT1z3+ajV6Mh/inK/+/Ts438Exp7KS6VyhquJMSTvLBTayp8t567Tq6BVJYIexjyl1CSg0eNfy6VhO2ul8o1NxS8EfemILvsGKawWyGGZOlSLIKvAUuBrUjLpDhiKLVgiKSEpluSUECEhKDHk0QGRRCVePP/HdNq87z+EbLWnxsBjqK8HRyUxH/ba+7vH6r4b0cHzUOM/3bU2MR/+7c8fuS2+fC3bKXjs6VkU0RVV4doE6+AG/y3/qMKyf83U2CuwgzZl5xEsAaUSCgK0+NyQiGP9Ft0xl88LFMMXcLf44CQ/7OXGiG0zFvLoE2xoei1d21Jf7Fgd53XBu+cU3JDSLnfWAgo88kyZZrqMg+x6H4+n9BLV+szX4LagynEF1t1QeYAn/40SFs8idCCQZDMhk9DUI12Ix3sIaqed89vaqqZmRs1wuqEzIkPYT4ykH7v5fTevNqKzmWIpgUiXz1RUCadOBA1xqbmBPNxPloReFiRype9SKGDRT+V+NiYXGUn/IxsT0BHyAOhewTrKaKqdPKD/b1AjK7VQ1ZkBDa3koG9SCuCP6bZLsqW43SoCJLD1Zfh5HHE/cHDjs8MqouOk6QTuO35yqa95d9q3nNv6NBbnta+ZybbXf99znuSoOvC95LrnvLfCbnrbb3eWQ8p1KNuR+ADjvNsMtpZPZK9XZK6z4YpLFfja/a2EJWVTmyFhbWpTBkUe0AMBJ1dytrCaizMchSnmqoegW85MijgUtaQ2F/4sBgoqlNxPYy9JbAp9+04fVhms4csX+FKmQ+FpwrjRsJDBD4VQ1gf3oeAFxQrYsuckdDf5kDD52veYQcrayuKv6xQaB7Ja/rVzrvoL9EsW6GDi0SCL2oReRdO0ClmTHrKbDURSzyT2QPlbmGUT2gM6nv5SsSVXM0VJLM42kMdRe4YS61/iZESsniCt3OxulX9Bc2a4xTEbRPuDxOcKv6JQBZzsrVj5E2h0MkcGtQHFIDPCKFOkvcKdtqKKk+mTAyPr1IRkFGUICCJoR93YA1GY9DLyNdI3xSiq58/tAfx+/bPSbej0Clgfn/KC5i52UgDgVomKllzITNg7R/mxYpCsNZkT5fB8MiclenisBjuyIqrBjlaCzbFliZkbvXSnApxMaNlOrTSvu0m79r9OLnrXaLGjrEDTnd3J3OYx1jn02OQRHch9+6nvUqtvhRdXtnlsFy2sOJSv233+7cXPSgD6bv5lDDj5rrrx73r9lUwjxJ1QiW9v+l13DyWINTCOyYs4+3N5WXSvR7EvR/b1APNCkcLc6UlN3svPu/F/QuLwl7DImGJVW7+fnzdsTLv+3mN7BXM/a49OLtI+t3/oc5qNpz6uzKa34JB72cxB/rx2c11p49kDgNUmDQXJnLV/onT2G/YdbEEPz54f7uL7+Kk8w5GcHCBOeu7KEHv0hpIPjXrxT8m+SrjY2qkxZbhKsJPP7m66VBXjNGIwjM50iTPCfMtwTnUiX/snsVUfeIkVpEBidPtCJE/OY9xTPi4Htl94cmnvD/MWYKYyj6EnmFoPJ9hVHY+vJIV5IXWCHuCbGCQ4+tB9+Y6waBVycXNXY866jt3yXhSbzmlTtzuJJ32z0Ro78AixCRklwDONJgvvZv3IiMpzKHM0/uSrOifhavFzxUQqUM02j/EyaDXvu53rc7YOzoOUmQyeBk1THLVvb4biPm31wzUjQvsQTqDQXx1O+gn7TuxlGx+UiLEu5R+7A7al0kffsTXcb/PF7Uzj0uEfpdgt4PjPfgZKA0Gl2s4jS2Ne/zu9qaZQJ9DZ0FHUe5Gafb0y7rs7Z/kxLHyc+He2yl6dtH7ZVn9gk1WUWzTGVpX9OWLGsczue11r9o9XNliapw0v2tuw8+TI/p5vH20d7R/UnFJWjKzR1QMgkV2v3kMBPf3G/jziH6eHGzvHR8fNBvbRweH2GYOkP8OlQrygkc1At2IUSZFo7cW64DE8UFUxXuUIHrmK8TkUnoeo8Q1D6E2UG9ui3AK5IUDWwAZsUdaDyFvCn6H2NUG3IGgdndxz4SNU45jYCiY1rCeghjOAAXSLlrC98TNDeUmZzQNDgMZUQEpywgLnDIe2Bm3opvx2Op0VMLEcb/RL8+ySZGDlD7Kl9kQRH6n18I6Dt8jqQ7n7YtefH0RdwfIYm5gBv0Y9wbB7dZWijxKmL1/BixmTWZfcSrpmvYPvTi+Qk48uLmMYbGdEdlG3eZ/vmrlju9F3O4N3sXtgS2VNUrISC2MN85QiK/b7y7jTkiY8FQttx7xT7BFdmJbONw7briypdG0WpZg2CM5RInQJEmp8jcHEa94ltK9GUSzT+RnJa9V/5EnAuvt/839w++axv6/j/Z/+Hm4sf//Ox7f/v+kf9VB5PQL8jJ9c3bX6yEL6nR7UQvNFqjo1IHfkuafJOMc5lNSe3MZ/9A+A1mod8aSpfcF/i9UdvWSDj8ZWdSN6hXnp1CT1PkuvuG/F0tY+G9qdEbJCpYX1aBVVJI8IJV/1YXjSLWxzbIACbITOgtCHYC4r9MiEncbLIAA3Kxm83+kp1F80GhCn21FVylIkEWRWmZLs22RCSbF4D3QiegSnRfRfEzBb9CPTYT5IDfL+RKozdFZ7nNeoA01/ZTmE1zE9Tfda2ChsG2j5tK9ji8jjYmHVbhEgaOwKvCIZzvKV+FVllYBklZ/04nP23eXAy2zSYmhj1fAywQ3qMNfZ3hxQOCaoffYDESgiahXtfgNAQhXT9NFOsuHeMtiCCL8dpQV88UDaBf4e71er9WBDklpUNbtQz7JF8UOiJWf0OVHiAO4re4Iy64sZJthPMtAGGTeRVo3KHhlX9BrKAeJIXLELwriwoz0TIZDn2cUoWCrhNGBoRoCOURoA400E0jN07zY0T5EFF9DAqEIiZFExfts9TmT3teM+htpBy9qJB+iq+NHGAQ8nMkLIykSPpwZECPv2kPyCqFX2t8oghRKh3QHY5l+rmkz4i1+wpBC8+k03S2yaT6cT+Z4vxtnrrqQjC7As8dptsyHUsqFvtQmRBDS5W1YYSUU0UaXYqGullggqvKo2S+zxSQdZtXK97jGt1HXh1FaVfFXjjS/XJHT2nLlQZNrj96lc+FDe/tpUcxDBhsV9XQ0IoR9zF/zbswYkNs1tKWxMscIR/JsFI9LbumYS3fsOT9riJYIxifWJeRFpSOliAZqEYogLKmYCrPVXEm9Es8vRv/+FSkrFL8LZgvISzsj9LafWUcY2Zd0uJJjlEYf6btYQoozCKuvRtbL0QV0le0Ui2yYj/Ph98pPF1ZG8ThdqNkprosS8iBBMuYzvIRMxH6tQ2V+tSMZYvAB6U1aV13CAl6FPVVcbwMQC9EvY5JO70dpBKxkdIrv0I+9alwx8H2tJu4DyYsyPFAJeVLYNEbOXWCmwoppx1YL3Vt2FV2YvGXssubdM2a67BrqVjpG31/9rARgdTRA6YROGlM58MjBvo863T7J8UzDiqqN3UZNT5gZ6AP3T4ya4pAUkxNBBZh2WxX8DbqWdOI6xohk/IomUjp2o1YqjVYGEjHtZdoutLbhdpvSY8vzpV9C+USkFgWfYLLRe8jQPKz7eVB3DeXB95DnwM6zFd3hWRLZ2mA9TUWX3Zyfv1aVZZSUUitAMVDp3BEaLJ7SEK/AXYf2HBpateXAGkPgXGIVKaM3hr0+2v+ufnwWWUFm6nJPFNwIsUJp1UuEDuBOT/JMjFfuXKvWu48zUoZHEtnWGWSjg0MvkjZpOjGsn2sHOtcnTKndPiH9ieXVDCUvJGAcdpLkKvQ7Xpv0TqlsryOVTrs1sYVuJVSxFnlaa9m6qav2VFYRxOyF8Z8tQ1WhUaospndYW+8f8XSuvJHAad/KJrxFH1cjBDqi33b0Vlf/LeEvwoZgRQhErPg1vRPyFYo0Oql3NmigSE1jVvNkKFaFbJCRTmRbqvI7IdtkNdpEQa6eAAfLaWtVcCXY4SK2vUDRtCOwetKA6Be16rFYVxqoDp4W2Uvw97JxouAKMk4pKFm9KAtrRZ5ArwH+YOyDGHaKNNMLKhxef0UuBB9+4cXZa8mmuhX151MtRheKPTGFQS/26v7RIdDeP6ofRmfbDpWT4yP4dnJcP4rOa5K/QMcDXRyVsVoswB0nOERP0uY+Jjm6cIgxXrUtcTF1UAUSe2gXF1dshFN1pPBJbCdsaHzzsIGdqXv1AP6G2hyf2K/39vYb/pVh8XkXg6nVG95X6u06YnzORtWK6KVdPDe1UtJFfqiFV4315X1DgQ0bzNdlwshkjk9gYJz21hvhJSCuMkU70X4TdrroPyLY8KLd6MSpkF2Z853/OuP3/hUxEcuM/nKDl+HqqLJtmlfP7MTrFti4grhSsPvJWNpfKf9zzWM2svxqJfpQif4SkdWBrBLUiBq++qUi6wSTSUZSqmmJPgwTGLAMGFWK/n+/TBdCIxdHpUqfVVF8ER0bRWmR5jMeQYzmH9mNPZj8n5qCPc4zIcZKyD2NgqFzCYNOEdHFJFCyYclM8c4GyOD3fwe5oiCxgogJxFiUhy2A9gmqUm4s4VBMciKCriw7n5eoLiCEt+jkl8V7gacYFvGLxwXeganrLPyaP82JRDU3IeTcljFj1S/hhetza5IX0qNW+9Nb6YRIL64mtpgCVy1xPVeAtOursKV6cISXXMzWBf07X0EfD61gd7SRB8sRUaB9z24OZG5zfV5J4rugUWQzSy93kulQW05SPYwWSGUJYkFC0A4tG/NZygnzIV7W4r01oN+qQBfkupYAG5jL5oBmlmbQTy0b4JLTCntZi2qqYfcboL84TSBdNjDFwoG2/HlFZqK0kNooTvSKTiOaXKkZec/5tNaRyfG6tvobRChdFbffdUKnsJfGgNMsGYYy0t6QmEkWWIg2HAEv1hscy7PTarU3PtrTjYAK5YRNoIMlQDbkXgo1K9TZzIXK8fb5Q8hbgycXi+8m9brKsAo5E08ukArdmdEVVsvm1U5zf9Cafan93qQtq0hwdtl9ZGbTi6PjTDF12RHLwx4aEg5mq2VX1xTA4WLKuKGzOu2BDk8D+rwV7ezsWFuzum2+862PqTCZhIarZPHwVMxzFnuVgLuEyRExSrehabPRJPPUyL9ibC4px4xAeSN0tHy2AqIUS2CGHvwoPwjjRKj+TuwXSUwKQ8P5ZALiEspfBGDqX6YgAwdKLb8uaNP6VVMjYz/pCsAqYOzRpi9rwQ43HkhaFTijStFBKYZCkNSjtjE5WwhwIhiCuBGSzmzdS9NBv+cZndsYJZN3gDwMIVWGQOQm2XilGxLhhagnfbudRYMx88WKClOPLlAnQg1LNlTIdHjegEcwJMuRFWnGz5BMC2W1YduXcezn8IOkObQUz+cGaZHOy2TbVDvqfGJ44ps/1dZNMgsvQXMrJpHVbXvOwp6Zei0lKLw7woxX8HqkAcu4oFzxeU2YgSJEE8ObygsqMjjW+vASQuYLFSTNOtTWkIyi5c9QRxih8QMSQLAZI3wSw9QSaLXmS56yJZhVn7AG5ckSH1svHZ2z2E3RGVR0CR3lA4tW/dIy9iOplAViqDjlir1IKabBjWVckcEKFhTNdJqSDzisLB3NQFYLDUGtr2akReRT2ZXPZ19xmnB1Uz1sIwrOyjDntdltBy8HRyl6dI0n6UexkGVVRfWH6XL5JE6PGKvZKYDNKdusxXBzmpUP88+Wewss84nirwU3yyBHi9RBkOKh1lxE7fvjY0b4t99HMMiI9Aq81b+YB8QU9Aee0YbZB1/+DFYfzw+5yTGor6wFKtJzP/nz1nRgJjpVDKyCb1rQL0zsccWZHnJ24yzJESmidJIE48pVy6d97YWxhh5bycmmJ5pdBFsfpfGyHKEKD1EVoDXF4tH700KHJePxzmhUYEVr4/dpBN23fPqnMB0xWzf/8Iw3NXBfxTcRA8X+KgOfQM70I/0nApw8g9jwkWyTND3p3rAFdoz/7ov55JFFHzQg6MK4CWM0mUAGbeN5SD+RZxKMEjnPBSMGhRcRC53CmLzZcbD/WtFXzVdPlUXZ2OPFq+dvmaXSQDHKhxLVge1BDDRC2ZS22X6kbk8k6UpvRdSZmFfhgdjFidHBVogATKdmhVMYo1OKRinixFjkG/VGDW8WYR0gFf3/bJGWmFd44AQ9RThVJdujSqmiYGKoOayXinSmA1zKF/5miSV8ENRxi6bcrj6CaZhWg2G2+XIJLROC3BYitL6ZYh3dEtS+jgdggz0X9jnJt0scdaqja5VZqMhZDdMYPxaf35ZbaIoMX4OCP7AtWB3I5fAFLKIiReO/XEUaWwaF2glZRLPpNzZHabIh+5r3bpub3OSAiS+by6mbZ/Nsns2zeTbP5tk8m2fzbJ7Ns3k2z+bZPJtn82yezbN5Ns/m+ZOefwFXtr+RAMgAAA==
