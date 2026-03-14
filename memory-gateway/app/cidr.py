"""CIDR helpers for ROMA policy (local vs WireGuard). Option B."""
import ipaddress
from typing import Literal

from app.config import LOCAL_CIDRS, WG_CIDRS


def ip_in_cidrs(ip_str: str, cidr_list: list[str]) -> bool:
    """True if IP is in any of the given CIDR ranges."""
    try:
        ip = ipaddress.ip_address(ip_str)
        for cidr in cidr_list:
            try:
                net = ipaddress.ip_network(cidr, strict=False)
                if ip in net:
                    return True
            except ValueError:
                continue
        return False
    except ValueError:
        return False


def get_zone(ip_str: str) -> Literal["local", "wg", "public"]:
    """
    Classify client IP into zone.
    local = LOCAL_CIDRS (trusted LAN)
    wg = WG_CIDRS (WireGuard, always fail-closed)
    public = everything else
    """
    if not ip_str:
        return "public"
    if ip_in_cidrs(ip_str, LOCAL_CIDRS):
        return "local"
    if ip_in_cidrs(ip_str, WG_CIDRS):
        return "wg"
    return "public"
