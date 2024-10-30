<?php
if ( !defined( 'MEDIAWIKI' ) ) {
    die( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
}

class VPNCheckLogger {
    public static function onRecentChangeSave( $recentChange ) {
        // ユーザーのIPアドレスを取得
        $userIP = $recentChange->getAttribute('rc_ip');
        if (!$userIP) {
            return true; // IPが取得できない場合は処理を中断
        }

        $vpnStatus = self::checkVPN($userIP);

        // リビジョンIDを取得
        $revisionId = $recentChange->getAttribute('rc_this_oldid');
        if (!$revisionId) {
            return true; // リビジョンIDが取得できない場合は処理を中断
        }

        // IPアドレス、VPNステータス、リビジョンIDをデータベースに保存
        self::logIP($userIP, $vpnStatus, $revisionId);

        // 編集履歴のコメントにVPNステータスを追加
        $summary = $recentChange->getAttribute('rc_comment') . " (VPN Status: $vpnStatus)";
        $recentChange->setAttribute('rc_comment', $summary);

        return true;
    }

    private static function checkVPN( $ip ) {
        $url = "https://spur.us/context/" . urlencode( $ip );
        $response = file_get_contents($url);

        if ($response === false) {
            return "Unknown"; // エラー時は「Unknown」として扱う
        }

        // VPNかどうかを判定し、VPNサービス名を返す
        if (preg_match('/<span>\s*(.*?)VPN/i', $response, $matches)) {
            return $matches[1] . " VPN"; // VPNサービス名を返す
        }
        return "Not VPN";
    }

    private static function logIP($ip, $vpnStatus, $revisionId) {
        $dbw = wfGetDB( DB_PRIMARY );
        $dbw->insert(
            'vpn_ip_log', // テーブル名
            [
                'ip_address' => $ip,
                'vpn_status' => $vpnStatus,
                'revision_id' => $revisionId,
                'timestamp' => wfTimestampNow()
            ],
            __METHOD__
        );
    }

    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
        $updater->addExtensionTable( 'vpn_ip_log', __DIR__ . '/vpn_ip_log.sql' );
    }
}
