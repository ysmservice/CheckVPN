CREATE TABLE IF NOT EXISTS vpn_ip_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARBINARY(16) NOT NULL,
    vpn_status VARCHAR(255) NOT NULL,
    revision_id INT NOT NULL,
    timestamp BINARY(14) NOT NULL
);
