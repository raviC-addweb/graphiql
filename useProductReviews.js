import { useState, useEffect, useCallback } from 'react';
import ProductReviewsPagination from './review-pagination-solution.js';

/**
 * React Hook for Product Reviews with Step-Based Pagination
 * 
 * @param {Object} graphqlClient - Apollo Client or similar GraphQL client
 * @param {string} productSlug - Product slug to fetch reviews for
 * @param {number} initialPageSize - Initial page size (default: 10)
 */
export const useProductReviews = (graphqlClient, productSlug, initialPageSize = 10) => {
  const [reviewsPagination] = useState(() => new ProductReviewsPagination(graphqlClient));
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    pageSize: initialPageSize,
    hasNextPage: false,
    hasPreviousPage: false,
    startIndex: 0,
    endIndex: 0,
    totalDisplayed: 0
  });

  /**
   * Fetch reviews for a specific page
   */
  const fetchPage = useCallback(async (page, pageSize = pagination.pageSize) => {
    if (!productSlug) return;

    setLoading(true);
    setError(null);

    try {
      const result = await reviewsPagination.getPage(productSlug, page, pageSize);
      setReviews(result.reviews);
      setPagination(result.pagination);
    } catch (err) {
      setError(err.message);
      setReviews([]);
    } finally {
      setLoading(false);
    }
  }, [productSlug, reviewsPagination, pagination.pageSize]);

  /**
   * Fetch reviews by offset and limit (0-based indexing)
   */
  const fetchRange = useCallback(async (offset, limit) => {
    if (!productSlug) return;

    setLoading(true);
    setError(null);

    try {
      const result = await reviewsPagination.getRange(productSlug, offset, limit);
      setReviews(result.reviews);
      setPagination(result.pagination);
    } catch (err) {
      setError(err.message);
      setReviews([]);
    } finally {
      setLoading(false);
    }
  }, [productSlug, reviewsPagination]);

  /**
   * Go to next page
   */
  const nextPage = useCallback(() => {
    if (pagination.hasNextPage) {
      fetchPage(pagination.currentPage + 1);
    }
  }, [pagination.hasNextPage, pagination.currentPage, fetchPage]);

  /**
   * Go to previous page
   */
  const previousPage = useCallback(() => {
    if (pagination.hasPreviousPage) {
      fetchPage(pagination.currentPage - 1);
    }
  }, [pagination.hasPreviousPage, pagination.currentPage, fetchPage]);

  /**
   * Go to a specific page
   */
  const goToPage = useCallback((page) => {
    if (page > 0) {
      fetchPage(page);
    }
  }, [fetchPage]);

  /**
   * Change page size and refetch
   */
  const changePageSize = useCallback((newSize) => {
    setPagination(prev => ({ ...prev, pageSize: newSize }));
    fetchPage(1, newSize);
  }, [fetchPage]);

  /**
   * Refresh current page
   */
  const refresh = useCallback(() => {
    reviewsPagination.clearCache(productSlug);
    fetchPage(pagination.currentPage);
  }, [productSlug, pagination.currentPage, fetchPage, reviewsPagination]);

  /**
   * Reset to first page
   */
  const reset = useCallback(() => {
    reviewsPagination.clearCache(productSlug);
    fetchPage(1);
  }, [productSlug, fetchPage, reviewsPagination]);

  // Initial load
  useEffect(() => {
    if (productSlug) {
      fetchPage(1);
    }
  }, [productSlug, fetchPage]);

  return {
    // Data
    reviews,
    loading,
    error,
    pagination,
    
    // Actions
    fetchPage,
    fetchRange,
    nextPage,
    previousPage,
    goToPage,
    changePageSize,
    refresh,
    reset,
    
    // Utilities
    clearCache: () => reviewsPagination.clearCache(productSlug),
    clearAllCache: () => reviewsPagination.clearAllCache()
  };
};

export default useProductReviews;