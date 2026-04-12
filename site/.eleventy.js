const isCI = process.env.CI === 'true';

module.exports = function (eleventyConfig) {
  // Passthroughs are only needed for the GitHub Pages build.
  // In local dev these files already exist in public/ and don't need copying.
  if (isCI) {
    eleventyConfig.addPassthroughCopy("images");
    eleventyConfig.addPassthroughCopy("og-preview.png");
    eleventyConfig.addPassthroughCopy("og-preview.svg");
    eleventyConfig.addPassthroughCopy("CNAME");
    eleventyConfig.addPassthroughCopy("robots.txt");
    eleventyConfig.addPassthroughCopy(".nojekyll");
    eleventyConfig.addPassthroughCopy("sitemap.xml");
  }

  return {
    dir: {
      // Local: output directly to public/ so /learn/ matches production URLs.
      // CI: output to _site/ so the Pages artifact doesn't include Laravel files.
      input: ".",
      output: isCI ? "_site" : "../public",
      layouts: "_layouts",
      includes: "_includes",
      data: "_data",
    },
    templateFormats: ["njk", "md", "html"],
    htmlTemplateEngine: "njk",
    markdownTemplateEngine: "njk",
  };
};
