# pghpoets.com content

The site is generated from these files on each request. No database.

- `poets/<slug>.yaml`: one poet per file. The filename is the URL: `/poets/<slug>`.
- `news.yaml`: the "What's New" list on the home page, newest first.

Page prose (About, Criteria) lives in `templates/pghpoets/`.

## Poet file

Copy an existing file and edit it. Rules:

- Quote every date (`"1925-02-18"`). Unquoted dates turn into timestamps.
- Every tie and dispute cites at least one key from `sources`.
- `kind` is one of: born, raised, studied, lived, worked, taught, published, edited.
- `inclusion.status` is `core` or `edge`.
- In prose, `_Title_` renders as italics. Leave a blank line between paragraphs.
- Sign the entry: set `entry.by` to your name once you have reviewed it.

## Check before deploying

    bin/console pghpoets:check

It lists any claim without a source, any unknown source key, and any unquoted date.
