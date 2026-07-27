{{-- Shared head furniture for every layout: icons and mobile browser chrome.

     theme-color is duplicated per colour-scheme so the phone's status bar
     matches the page instead of sitting as a white stripe over a dark app.
     The values are the two --canvas tokens from app.css, restated here because
     a <meta> cannot read a CSS variable. --}}
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#f9fafb">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0c1015">
