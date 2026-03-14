"""CIDR helpers for ROMA policy (local vs WireGuard)."""
import ipaddress


def is_private_or_localhost(ip_str: str) -> bool:
    """True if IP is loopback or private (RFC 1918)."""
    try:
        ip = ipaddress.ip_address(ip_str)
        return ip.is_loopback or ip.is_private
    except ValueError:
        return False
