/**
 * Usage Examples for WooCommerce GraphQL Product Reviews Step-Based Pagination
 */

// ===== EXAMPLE 1: Basic Usage with Apollo Client =====

import { ApolloClient, InMemoryCache } from '@apollo/client';
import ProductReviewsPagination from './review-pagination-solution.js';

// Setup Apollo Client
const client = new ApolloClient({
  uri: 'https://your-woocommerce-site.com/graphql',
  cache: new InMemoryCache(),
  headers: {
    // Add any necessary headers (authentication, etc.)
  }
});

// Create pagination instance
const reviewsPagination = new ProductReviewsPagination(client);

// Example usage
async function basicExample() {
  try {
    // Get first page (reviews 1-10)
    const page1 = await reviewsPagination.getPage('product-slug', 1, 10);
    console.log('Page 1:', page1);

    // Get second page (reviews 11-20)
    const page2 = await reviewsPagination.getPage('product-slug', 2, 10);
    console.log('Page 2:', page2);

    // Get specific range (reviews 25-35)
    const range = await reviewsPagination.getRange('product-slug', 24, 11);
    console.log('Custom Range:', range);

  } catch (error) {
    console.error('Error:', error);
  }
}

// ===== EXAMPLE 2: React Component Integration =====

import React from 'react';
import { ApolloProvider } from '@apollo/client';
import ProductReviewsComponent from './ProductReviewsComponent.jsx';

function App() {
  return (
    <ApolloProvider client={client}>
      <div className="App">
        <ProductReviewsComponent 
          graphqlClient={client}
          productSlug="example-product-slug"
        />
      </div>
    </ApolloProvider>
  );
}

// ===== EXAMPLE 3: Using with React Hook Directly =====

import React, { useState } from 'react';
import { useProductReviews } from './useProductReviews.js';

function CustomReviewsComponent({ productSlug }) {
  const [offsetInput, setOffsetInput] = useState(0);
  const [limitInput, setLimitInput] = useState(10);
  
  const {
    reviews,
    loading,
    error,
    pagination,
    fetchRange,
    goToPage,
    nextPage,
    previousPage
  } = useProductReviews(client, productSlug, 10);

  const handleCustomRange = () => {
    fetchRange(offsetInput, limitInput);
  };

  return (
    <div>
      {/* Custom offset/limit controls */}
      <div>
        <input 
          type="number" 
          value={offsetInput} 
          onChange={(e) => setOffsetInput(parseInt(e.target.value) || 0)}
          placeholder="Start index (offset)"
        />
        <input 
          type="number" 
          value={limitInput} 
          onChange={(e) => setLimitInput(parseInt(e.target.value) || 10)}
          placeholder="Number of items (limit)"
        />
        <button onClick={handleCustomRange}>Get Range</button>
      </div>

      {/* Display current range info */}
      <p>
        Showing items {pagination.startIndex} to {pagination.endIndex}
      </p>

      {/* Reviews display */}
      {loading && <p>Loading...</p>}
      {error && <p>Error: {error}</p>}
      {reviews.map((review, index) => (
        <div key={review.id}>
          <h4>Review #{pagination.startIndex + index + 1}</h4>
          <p><strong>Author:</strong> {review.author?.node?.name}</p>
          <p><strong>Rating:</strong> {review.rating}/5</p>
          <p>{review.content}</p>
        </div>
      ))}

      {/* Simple pagination */}
      <div>
        <button onClick={previousPage} disabled={!pagination.hasPreviousPage}>
          Previous
        </button>
        <span>Page {pagination.currentPage}</span>
        <button onClick={nextPage} disabled={!pagination.hasNextPage}>
          Next
        </button>
      </div>
    </div>
  );
}

// ===== EXAMPLE 4: Non-React Usage (Vanilla JS) =====

async function vanillaJSExample() {
  const reviewsPagination = new ProductReviewsPagination(client);
  
  // Function to mimic your original offset/size approach
  async function getReviewsWithOffsetAndSize(productSlug, offset, size) {
    try {
      const result = await reviewsPagination.getRange(productSlug, offset, size);
      
      return {
        reviews: result.reviews,
        meta: {
          offset: offset,
          size: size,
          total_returned: result.reviews.length,
          has_more: result.pagination.hasNextPage,
          current_page: result.pagination.currentPage
        }
      };
    } catch (error) {
      throw new Error(`Failed to fetch reviews: ${error.message}`);
    }
  }

  // Usage examples that match your original query pattern
  
  // Original: offset: 0, size: 10 (first 10 reviews)
  const firstBatch = await getReviewsWithOffsetAndSize('product-slug', 0, 10);
  console.log('First 10 reviews:', firstBatch);

  // Original: offset: 10, size: 10 (next 10 reviews)
  const secondBatch = await getReviewsWithOffsetAndSize('product-slug', 10, 10);
  console.log('Reviews 11-20:', secondBatch);

  // Original: offset: 20, size: 5 (reviews 21-25)
  const customBatch = await getReviewsWithOffsetAndSize('product-slug', 20, 5);
  console.log('Reviews 21-25:', customBatch);
}

// ===== EXAMPLE 5: With Error Handling and Retry Logic =====

class RobustReviewsFetcher {
  constructor(graphqlClient, maxRetries = 3) {
    this.pagination = new ProductReviewsPagination(graphqlClient);
    this.maxRetries = maxRetries;
  }

  async getReviewsWithRetry(productSlug, offset, size, retryCount = 0) {
    try {
      const result = await this.pagination.getRange(productSlug, offset, size);
      return result;
    } catch (error) {
      if (retryCount < this.maxRetries) {
        console.log(`Retry ${retryCount + 1}/${this.maxRetries} for ${productSlug}`);
        await this.delay(1000 * (retryCount + 1)); // Exponential backoff
        return this.getReviewsWithRetry(productSlug, offset, size, retryCount + 1);
      }
      throw error;
    }
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// ===== EXAMPLE 6: Batch Processing Multiple Products =====

async function batchProcessExample() {
  const reviewsPagination = new ProductReviewsPagination(client);
  const productSlugs = ['product-1', 'product-2', 'product-3'];
  
  const allReviews = {};
  
  for (const slug of productSlugs) {
    try {
      // Get first 20 reviews for each product
      const reviews = await reviewsPagination.getRange(slug, 0, 20);
      allReviews[slug] = reviews;
      
      // Clear cache for each product to save memory
      reviewsPagination.clearCache(slug);
    } catch (error) {
      console.error(`Failed to fetch reviews for ${slug}:`, error);
      allReviews[slug] = { error: error.message };
    }
  }
  
  return allReviews;
}

// Export for use
export {
  basicExample,
  vanillaJSExample,
  RobustReviewsFetcher,
  batchProcessExample
};