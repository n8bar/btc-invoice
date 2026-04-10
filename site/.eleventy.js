module.exports = function (eleventyConfig) {
  // Static assets — pass through unchanged
  eleventyConfig.addPassthroughCopy("images");
  eleventyConfig.addPassthroughCopy("og-preview.png");
  eleventyConfig.addPassthroughCopy("og-preview.svg");
  eleventyConfig.addPassthroughCopy("CNAME");
  eleventyConfig.addPassthroughCopy("robots.txt");
  eleventyConfig.addPassthroughCopy(".nojekyll");

  // Sitemap is maintained manually for now; pass through as-is
  eleventyConfig.addPassthroughCopy("sitemap.xml");

  return {
    dir: {
      input: ".",
      output: "../public/content",
      layouts: "_layouts",
      includes: "_includes",
      data: "_data",
    },
    templateFormats: ["njk", "md", "html"],
    htmlTemplateEngine: "njk",
    markdownTemplateEngine: "njk",
  };
};
