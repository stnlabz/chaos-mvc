<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
>
    <xsl:output method="html" encoding="UTF-8" indent="yes" />

    <xsl:template match="/">
        <html lang="en">
            <head>
                <meta charset="UTF-8" />
                <meta
                    name="viewport"
                    content="width=device-width, initial-scale=1"
                />
                <title>
                    <xsl:value-of select="rss/channel/title" /> RSS Feed
                </title>
                <style>
                    body {
                        margin: 0;
                        font-family:
                            system-ui,
                            -apple-system,
                            BlinkMacSystemFont,
                            "Segoe UI",
                            sans-serif;
                        background: #f5f5f5;
                        color: #202020;
                    }

                    main {
                        width: min(900px, calc(100% - 2rem));
                        margin: 3rem auto;
                    }

                    header {
                        margin-bottom: 2rem;
                        padding-bottom: 1.5rem;
                        border-bottom: 1px solid #d7d7d7;
                    }

                    h1 {
                        margin: 0 0 .5rem;
                        font-size: 2rem;
                    }

                    .site-link {
                        display: inline-block;
                        margin-top: .5rem;
                    }

                    .feed-note {
                        margin-top: 1rem;
                        color: #666;
                        font-size: .95rem;
                    }

                    article {
                        margin: 0 0 1.25rem;
                        padding: 1.5rem;
                        background: #fff;
                        border: 1px solid #ddd;
                        border-radius: .5rem;
                    }

                    article h2 {
                        margin: 0 0 .5rem;
                        font-size: 1.35rem;
                    }

                    article p {
                        line-height: 1.6;
                    }

                    time {
                        display: block;
                        margin-bottom: .75rem;
                        color: #666;
                        font-size: .9rem;
                    }

                    a {
                        color: inherit;
                        text-decoration-thickness: .08em;
                        text-underline-offset: .15em;
                    }
                </style>
            </head>
            <body>
                <main>
                    <header>
                        <h1>
                            <xsl:value-of select="rss/channel/title" />
                        </h1>

                        <p>
                            <xsl:value-of select="rss/channel/description" />
                        </p>

                        <a class="site-link">
                            <xsl:attribute name="href">
                                <xsl:value-of select="rss/channel/link" />
                            </xsl:attribute>
                            Visit website
                        </a>

                        <p class="feed-note">
                            This is an RSS feed. Subscribe with your preferred
                            feed reader using this page's URL.
                        </p>
                    </header>

                    <xsl:for-each select="rss/channel/item">
                        <article>
                            <h2>
                                <a>
                                    <xsl:attribute name="href">
                                        <xsl:value-of select="link" />
                                    </xsl:attribute>
                                    <xsl:value-of select="title" />
                                </a>
                            </h2>

                            <xsl:if test="pubDate">
                                <time>
                                    <xsl:value-of select="pubDate" />
                                </time>
                            </xsl:if>

                            <xsl:if test="description">
                                <p>
                                    <xsl:value-of select="description" />
                                </p>
                            </xsl:if>
                        </article>
                    </xsl:for-each>
                </main>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
