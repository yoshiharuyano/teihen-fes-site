#!/bin/bash
# ═══════════════════════════════════════════════
# サマテイ 2026 — WSL ローカルプレビュー セットアップ
# ═══════════════════════════════════════════════
#
# 使い方:
#   1. ZIPをWSLにコピー
#   2. bash setup.sh
#   3. ブラウザで http://localhost:8080 を開く

set -e

SITE_DIR="$HOME/summatei-site"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo ""
echo "  ╔══════════════════════════════════════════╗"
echo "  ║  サマテイ 2026 セットアップ               ║"
echo "  ╚══════════════════════════════════════════╝"
echo ""

# ディレクトリ作成
if [ -d "$SITE_DIR" ]; then
  echo "  ⚠ $SITE_DIR は既に存在します"
  read -p "  上書きしますか？ (y/N): " confirm
  if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "  中止しました"
    exit 0
  fi
  rm -rf "$SITE_DIR"
fi

mkdir -p "$SITE_DIR"

# ファイルコピー
echo "  📁 ファイルをコピー中..."
cp -r "$SCRIPT_DIR"/index.html "$SITE_DIR/"
cp -r "$SCRIPT_DIR"/_htaccess "$SITE_DIR/"
cp -r "$SCRIPT_DIR"/robots.txt "$SITE_DIR/"
cp -r "$SCRIPT_DIR"/llms.txt "$SITE_DIR/"
cp -r "$SCRIPT_DIR"/server.py "$SITE_DIR/"
cp -r "$SCRIPT_DIR"/vol1 "$SITE_DIR/"
cp -r "$SCRIPT_DIR"/vol2 "$SITE_DIR/"

echo ""
echo "  ✓ セットアップ完了！"
echo ""
echo "  ディレクトリ: $SITE_DIR"
echo ""
echo "  ── 起動方法 ──"
echo ""
echo "    cd $SITE_DIR"
echo "    python3 server.py"
echo ""
echo "  ブラウザで http://localhost:8080 を開いてください"
echo ""
echo "  ── 確認ポイント ──"
echo ""
echo "    1. TOP: http://localhost:8080/"
echo "       → ロゴ画像・カウントダウン・12セクション"
echo ""
echo "    2. 第一回アーカイブ: http://localhost:8080/vol1/"
echo "       → アーカイブバナー表示・リンクがvol1内で完結"
echo ""
echo "    3. リダイレクト: http://localhost:8080/artists.html"
echo "       → /vol1/artists.html に301リダイレクト"
echo ""
echo "    4. 旧URL: http://localhost:8080/summatei.html"
echo "       → / に301リダイレクト"
echo ""
echo "    5. ロゴ画像: /vol2/img/summatei-logo.png"
echo "       → ヒーローセクションに表示"
echo ""

# 自動起動するか聞く
read -p "  今すぐサーバーを起動しますか？ (Y/n): " start
if [ "$start" != "n" ] && [ "$start" != "N" ]; then
  cd "$SITE_DIR"
  python3 server.py
fi
