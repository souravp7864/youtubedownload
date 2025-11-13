<?php
// This file runs the Telegram bot
require_once 'vendor/autoload.php';

// Import the Python bot logic
$pythonScript = <<<'PYTHON'
import os
import logging
import tempfile
import asyncio
import json
from pathlib import Path
from yt_dlp import YoutubeDL, DownloadError
from telegram import (
    Update,
    InlineKeyboardButton,
    InlineKeyboardMarkup
)
from telegram.ext import (
    Application,
    CommandHandler,
    MessageHandler,
    CallbackQueryHandler,
    ContextTypes,
    filters,
)
from telegram.request import HTTPXRequest

# ==========================
# CONFIG
# ==========================
BOT_TOKEN = os.getenv('BOT_TOKEN', '8507471476:AAHkLlfP4uZ8DwNsoffhDPQsfh61QoX9aZc')

# Use persistent storage directory
DATA_DIR = Path("/var/www/html/data")
DATA_DIR.mkdir(exist_ok=True)

# Folder for temp downloads
DOWNLOAD_DIR = DATA_DIR / "downloads"
DOWNLOAD_DIR.mkdir(exist_ok=True)

# Users storage
USERS_JSON = DATA_DIR / "users.json"

# ==========================
# LOGGING
# ==========================
logging.basicConfig(
    format="%(asctime)s - %(levelname)s - %(name)s - %(message)s",
    level=logging.INFO,
    handlers=[
        logging.FileHandler(DATA_DIR / "bot.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# ==========================
# USER DATA MANAGEMENT
# ==========================
def load_users():
    try:
        if USERS_JSON.exists():
            with open(USERS_JSON, 'r') as f:
                return json.load(f)
    except Exception as e:
        logger.error(f"Error loading users: {e}")
    return {}

def save_users(users_data):
    try:
        with open(USERS_JSON, 'w') as f:
            json.dump(users_data, f, indent=2)
    except Exception as e:
        logger.error(f"Error saving users: {e}")

# ==========================
# /start COMMAND
# ==========================
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    users_data = load_users()
    user_id = str(update.effective_user.id)
    
    if user_id not in users_data:
        users_data[user_id] = {
            'username': update.effective_user.username,
            'first_name': update.effective_user.first_name,
            'last_name': update.effective_user.last_name,
            'started_at': update.message.date.isoformat()
        }
        save_users(users_data)
    
    await update.message.reply_text(
        "🎉 *Welcome!*\n\n"
        "Send me any YouTube link and choose:\n"
        "🎥 *Download Video*\n"
        "🎧 *Download MP3 Audio*\n",
        parse_mode="Markdown"
    )

# ==========================
# HANDLE RECEIVED YOUTUBE LINK
# ==========================
async def handle_link(update: Update, context: ContextTypes.DEFAULT_TYPE):
    text = (update.message.text or "").strip()
    logger.info("Received text: %s", text)

    if "youtube.com" not in text and "youtu.be" not in text:
        await update.message.reply_text("❌ Please send a valid YouTube link.")
        return

    context.user_data["url"] = text

    buttons = [
        [InlineKeyboardButton("🎥 Download Video", callback_data="video")],
        [InlineKeyboardButton("🎧 Download MP3 Audio", callback_data="audio")],
    ]

    await update.message.reply_text(
        "Select download format:",
        reply_markup=InlineKeyboardMarkup(buttons)
    )

# ==========================
# HANDLE BUTTON CLICKS
# ==========================
async def handle_choice(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()  # Acknowledge

    url = context.user_data.get("url")
    choice = query.data

    await query.edit_message_text(f"📥 Downloading {choice.upper()}... Please wait ⏳")

    try:
        file_path = await download_media(url, choice)
    except Exception as e:
        logger.exception("Download error")
        await query.message.reply_text(f"❌ Download Failed:\n{e}")
        return

    if not file_path.exists():
        await query.message.reply_text("❌ Error: File not found after download!")
        return

    try:
        await send_file(context, update, file_path)
    except Exception as e:
        logger.exception("Upload error")
        await query.message.reply_text(f"❌ Upload Failed:\n{e}")
    finally:
        try:
            file_path.unlink()
        except:
            pass

# ==========================
# DOWNLOAD WITH YT-DLP
# ==========================
async def download_media(url: str, media_type: str) -> Path:
    loop = asyncio.get_running_loop()

    outtmpl = str(DOWNLOAD_DIR / "%(id)s.%(ext)s")

    ydl_opts = {"outtmpl": outtmpl, "quiet": True, "noplaylist": True}

    if media_type == "audio":
        ydl_opts.update({
            "format": "bestaudio/best",
            "postprocessors": [{
                "key": "FFmpegExtractAudio",
                "preferredcodec": "mp3",
                "preferredquality": "192"
            }],
        })
    else:  # video
        ydl_opts["format"] = "best[ext=mp4]/best"

    def run_ydl():
        with YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(url, download=True)
            base = ydl.prepare_filename(info)
            return (
                Path(base).with_suffix(".mp3")
                if media_type == "audio"
                else Path(base).with_suffix(".mp4")
            )

    return await loop.run_in_executor(None, run_ydl)

# ==========================
# SEND FILE TO TELEGRAM
# ==========================
async def send_file(context, update: Update, file_path: Path):
    chat_id = update.effective_chat.id
    logger.info(f"Sending file: {file_path}")

    with open(file_path, "rb") as f:
        await context.bot.send_document(
            chat_id=chat_id,
            document=f,
            filename=file_path.name
        )

# ==========================
# MAIN
# ==========================
def main():
    # FIXED: Correct parameters for python-telegram-bot 21.x
    request = HTTPXRequest(
        read_timeout=60,
        write_timeout=60,
        connect_timeout=60,
        pool_timeout=60,
    )

    app = Application.builder().token(BOT_TOKEN).request(request).build()

    app.add_handler(CommandHandler("start", start))
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_link))
    app.add_handler(CallbackQueryHandler(handle_choice))

    logger.info("🤖 Bot is running...")
    app.run_polling(allowed_updates=["message", "callback_query"])

if __name__ == "__main__":
    main()
PYTHON;

// Write the Python script to a file
file_put_contents('/var/www/html/bot_runner.py', $pythonScript);

// Execute the Python bot
echo "Starting Telegram Bot...\n";
system('python3 /var/www/html/bot_runner.py 2>&1');
?>