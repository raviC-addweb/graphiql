import React from 'react';
import { useProductReviews } from './useProductReviews';

/**
 * Product Reviews Component with Step-Based Pagination
 * 
 * This component demonstrates how to use the useProductReviews hook
 * for step-based pagination that mimics offset/limit behavior
 */
const ProductReviewsComponent = ({ graphqlClient, productSlug }) => {
  const {
    reviews,
    loading,
    error,
    pagination,
    nextPage,
    previousPage,
    goToPage,
    changePageSize,
    fetchRange,
    refresh
  } = useProductReviews(graphqlClient, productSlug, 10);

  // Handle direct offset/limit input (like your original requirement)
  const handleOffsetLimitFetch = () => {
    const offset = parseInt(document.getElementById('offset').value) || 0;
    const limit = parseInt(document.getElementById('limit').value) || 10;
    fetchRange(offset, limit);
  };

  // Generate page numbers for pagination controls
  const generatePageNumbers = () => {
    const pages = [];
    const currentPage = pagination.currentPage;
    const startPage = Math.max(1, currentPage - 2);
    const endPage = startPage + 4;

    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }
    return pages;
  };

  if (error) {
    return (
      <div className="error">
        <h3>Error loading reviews</h3>
        <p>{error}</p>
        <button onClick={refresh}>Retry</button>
      </div>
    );
  }

  return (
    <div className="product-reviews">
      <h2>Product Reviews</h2>
      
      {/* Offset/Limit Controls (like your original requirement) */}
      <div className="controls-section">
        <h3>Direct Offset/Limit Control</h3>
        <div className="offset-controls">
          <label>
            Offset (start index):
            <input id="offset" type="number" defaultValue="0" min="0" />
          </label>
          <label>
            Limit (number of items):
            <input id="limit" type="number" defaultValue="10" min="1" max="50" />
          </label>
          <button onClick={handleOffsetLimitFetch} disabled={loading}>
            Fetch Range
          </button>
        </div>
      </div>

      {/* Page Size Control */}
      <div className="page-size-control">
        <label>
          Reviews per page:
          <select 
            value={pagination.pageSize} 
            onChange={(e) => changePageSize(parseInt(e.target.value))}
            disabled={loading}
          >
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={20}>20</option>
            <option value={50}>50</option>
          </select>
        </label>
      </div>

      {/* Current Page Info */}
      <div className="pagination-info">
        <p>
          Showing reviews {pagination.startIndex + 1} to {pagination.endIndex + 1}
          {pagination.totalDisplayed > 0 && ` (${pagination.totalDisplayed} total on this page)`}
        </p>
        <p>Current Page: {pagination.currentPage}</p>
      </div>

      {/* Loading State */}
      {loading && (
        <div className="loading">
          <p>Loading reviews...</p>
        </div>
      )}

      {/* Reviews List */}
      <div className="reviews-list">
        {reviews.length > 0 ? (
          reviews.map((review, index) => (
            <div key={review.id} className="review-item">
              <div className="review-header">
                <span className="review-index">
                  #{pagination.startIndex + index + 1}
                </span>
                <span className="review-author">
                  {review.author?.node?.name || 'Anonymous'}
                </span>
                <span className="review-rating">
                  {'★'.repeat(review.rating || 0)}{'☆'.repeat(5 - (review.rating || 0))}
                </span>
                <span className="review-date">
                  {new Date(review.date).toLocaleDateString()}
                </span>
              </div>
              <div className="review-content">
                {review.content}
              </div>
            </div>
          ))
        ) : (
          !loading && <p>No reviews found.</p>
        )}
      </div>

      {/* Pagination Controls */}
      <div className="pagination-controls">
        <button 
          onClick={previousPage} 
          disabled={!pagination.hasPreviousPage || loading}
        >
          Previous
        </button>

        {generatePageNumbers().map(pageNum => (
          <button
            key={pageNum}
            onClick={() => goToPage(pageNum)}
            disabled={loading}
            className={pageNum === pagination.currentPage ? 'active' : ''}
          >
            {pageNum}
          </button>
        ))}

        <button 
          onClick={nextPage} 
          disabled={!pagination.hasNextPage || loading}
        >
          Next
        </button>
      </div>

      {/* Direct Page Navigation */}
      <div className="direct-navigation">
        <label>
          Go to page:
          <input 
            type="number" 
            min="1" 
            defaultValue={pagination.currentPage}
            onKeyPress={(e) => {
              if (e.key === 'Enter') {
                const page = parseInt(e.target.value);
                if (page > 0) {
                  goToPage(page);
                }
              }
            }}
          />
        </label>
      </div>

      {/* Debug Info */}
      <details className="debug-info">
        <summary>Debug Information</summary>
        <pre>{JSON.stringify(pagination, null, 2)}</pre>
      </details>
    </div>
  );
};

export default ProductReviewsComponent;