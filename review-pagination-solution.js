/**
 * WooCommerce GraphQL Product Reviews Step-Based Pagination Solution
 * 
 * This solution provides offset-like pagination using cursor-based GraphQL queries
 */

class ProductReviewsPagination {
  constructor(graphqlClient) {
    this.client = graphqlClient;
    this.cursorCache = new Map(); // Cache cursors for each page
    this.totalCount = null;
    this.pageSize = 10;
  }

  /**
   * GraphQL query for fetching product reviews
   */
  getReviewsQuery = `
    query GetProductReviews($productSlug: ID!, $first: Int!, $after: String) {
      product(id: $productSlug, idType: SLUG) {
        id
        name
        reviews(
          first: $first
          after: $after
          where: {
            orderby: COMMENT_DATE
            order: DESC
          }
        ) {
          nodes {
            id
            content
            date
            rating
            author {
              node {
                id
                name
              }
            }
          }
          pageInfo {
            hasNextPage
            hasPreviousPage
            startCursor
            endCursor
          }
          edges {
            cursor
            node {
              id
            }
          }
        }
      }
    }
  `;

  /**
   * Get reviews for a specific page (1-indexed)
   * @param {string} productSlug - Product slug
   * @param {number} page - Page number (1, 2, 3, etc.)
   * @param {number} size - Number of items per page
   * @returns {Promise<Object>} Paginated reviews data
   */
  async getPage(productSlug, page = 1, size = 10) {
    this.pageSize = size;
    
    // If requesting page 1, start fresh
    if (page === 1) {
      return this.getFirstPage(productSlug, size);
    }

    // For subsequent pages, we need to build up to that page
    return this.getSpecificPage(productSlug, page, size);
  }

  /**
   * Get the first page of reviews
   */
  async getFirstPage(productSlug, size) {
    const variables = {
      productSlug,
      first: size
    };

    try {
      const result = await this.client.query({
        query: this.getReviewsQuery,
        variables
      });

      const reviewsData = result.data.product.reviews;
      
      // Cache the cursor for page 2
      if (reviewsData.pageInfo.hasNextPage) {
        this.cursorCache.set(`${productSlug}-page-2`, reviewsData.pageInfo.endCursor);
      }

      return this.formatResponse(reviewsData, 1, size, productSlug);
    } catch (error) {
      throw new Error(`Failed to fetch reviews: ${error.message}`);
    }
  }

  /**
   * Get a specific page by building up cursors
   */
  async getSpecificPage(productSlug, targetPage, size) {
    let currentPage = 1;
    let cursor = null;
    
    // Check if we have the cursor cached
    const cacheKey = `${productSlug}-page-${targetPage}`;
    if (this.cursorCache.has(cacheKey)) {
      cursor = this.cursorCache.get(cacheKey);
    } else {
      // Build up to the target page
      while (currentPage < targetPage) {
        const pageKey = `${productSlug}-page-${currentPage + 1}`;
        
        if (this.cursorCache.has(pageKey)) {
          cursor = this.cursorCache.get(pageKey);
          currentPage++;
        } else {
          // Fetch the current page to get the next cursor
          const result = await this.fetchPageWithCursor(productSlug, cursor, size);
          if (result.pageInfo.hasNextPage) {
            cursor = result.pageInfo.endCursor;
            this.cursorCache.set(`${productSlug}-page-${currentPage + 1}`, cursor);
          } else {
            // No more pages available
            throw new Error(`Page ${targetPage} does not exist`);
          }
          currentPage++;
        }
      }
    }

    // Now fetch the target page
    const result = await this.fetchPageWithCursor(productSlug, cursor, size);
    
    // Cache the next page cursor if available
    if (result.pageInfo.hasNextPage) {
      this.cursorCache.set(`${productSlug}-page-${targetPage + 1}`, result.pageInfo.endCursor);
    }

    return this.formatResponse(result, targetPage, size, productSlug);
  }

  /**
   * Fetch a page using a cursor
   */
  async fetchPageWithCursor(productSlug, cursor, size) {
    const variables = {
      productSlug,
      first: size,
      ...(cursor && { after: cursor })
    };

    try {
      const result = await this.client.query({
        query: this.getReviewsQuery,
        variables
      });

      return result.data.product.reviews;
    } catch (error) {
      throw new Error(`Failed to fetch reviews with cursor: ${error.message}`);
    }
  }

  /**
   * Format the response with pagination metadata
   */
  formatResponse(reviewsData, currentPage, pageSize, productSlug) {
    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = startIndex + reviewsData.nodes.length - 1;

    return {
      reviews: reviewsData.nodes,
      pagination: {
        currentPage,
        pageSize,
        startIndex,
        endIndex,
        hasNextPage: reviewsData.pageInfo.hasNextPage,
        hasPreviousPage: currentPage > 1,
        totalDisplayed: reviewsData.nodes.length
      },
      productSlug,
      _internal: {
        startCursor: reviewsData.pageInfo.startCursor,
        endCursor: reviewsData.pageInfo.endCursor
      }
    };
  }

  /**
   * Get a range of reviews (like offset/limit)
   * @param {string} productSlug - Product slug
   * @param {number} offset - Starting index (0-based)
   * @param {number} limit - Number of items to fetch
   */
  async getRange(productSlug, offset, limit) {
    const page = Math.floor(offset / limit) + 1;
    const pageSize = limit;
    
    const result = await this.getPage(productSlug, page, pageSize);
    
    // If offset is not at the start of a page, we need to slice the results
    const offsetInPage = offset % limit;
    if (offsetInPage > 0) {
      result.reviews = result.reviews.slice(offsetInPage);
      result.pagination.startIndex = offset;
      result.pagination.endIndex = offset + result.reviews.length - 1;
    }

    return result;
  }

  /**
   * Clear cache for a specific product
   */
  clearCache(productSlug) {
    const keysToDelete = [];
    for (const key of this.cursorCache.keys()) {
      if (key.startsWith(`${productSlug}-`)) {
        keysToDelete.push(key);
      }
    }
    keysToDelete.forEach(key => this.cursorCache.delete(key));
  }

  /**
   * Clear all cache
   */
  clearAllCache() {
    this.cursorCache.clear();
  }
}

// Usage Examples
export default ProductReviewsPagination;

// Example usage with Apollo Client:
/*
import { ApolloClient } from '@apollo/client';
import ProductReviewsPagination from './review-pagination-solution.js';

const client = new ApolloClient({
  uri: 'https://your-site.com/graphql',
  // ... other config
});

const reviewsPagination = new ProductReviewsPagination(client);

// Get page 1 (first 10 reviews)
const page1 = await reviewsPagination.getPage('product-slug', 1, 10);

// Get page 2 (reviews 11-20)
const page2 = await reviewsPagination.getPage('product-slug', 2, 10);

// Get specific range (like offset/limit)
const range = await reviewsPagination.getRange('product-slug', 20, 5); // Get 5 reviews starting from index 20

console.log('Page 1:', page1);
console.log('Page 2:', page2);
console.log('Range:', range);
*/