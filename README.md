# Harbufeed

**Your videos. Your rules.** A calm, curated video frontend. YouTube first, with a source-adapter architecture planned for other platforms.

## Prototype v0.1.0

Open `index.html` in a browser or serve the folder with a static server, for example `python3 -m http.server 8080`.

Includes: dark/light mode, responsive layout, feed/topic filters, Following, Discover, Watch Later, watched/hidden state, local persistence, settings and a video-preview modal.

**Important:** The video cards are illustrative demo fixtures, not real YouTube results. Channel names and custom topics are stored as preferences; they are not resolved against YouTube yet. Shorts filtering is represented as a UI preference, and the sample fixtures contain no Shorts. The player intentionally shows a demo placeholder because no sample videos include verified YouTube IDs. No API key is required at this stage.

## Next phase

Create a server-side YouTube Data API adapter. Normalize each source response to `{id, source, videoId, title, channel, topic, minutes, published, thumbnail}`. Resolve channel URLs/IDs, fetch uploads efficiently, detect short-form content as reliably as supported, and cache responses. Later add other source adapters without rewriting the UI. Do not expose API keys in client-side JavaScript.

## Privacy

Preferences are stored in browser localStorage only. No accounts, tracking, or server requests for preferences. Demo thumbnails load from Unsplash.

---

.::MADE BY H∆RBUGΛП::. · v0.1.0
