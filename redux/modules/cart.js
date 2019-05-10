export const GET_CART = "cart/GET_CART";
export const UPDATE_CART = "cart/UPDATE_CART";
export const ADD_ITEM = "cart/ADD_ITEM";
export const DELETE_ITEM = "cart/DELETE_ITEM";
export const UPDATE_ITEM = "cart/UPDATE_ITEM";
export const ADD_COUPON = "cart/ADD_COUPON";
export const SET_COUPON_ERROR = "cart/SET_COUPON_ERROR";

const initialState = {
  couponError: false
};

export default function reducer(state = initialState, action) {
  switch (action.type) {
    case UPDATE_CART:
      const { cart } = action.payload;
      return {
        ...state,
        ...cart
      };
    case SET_COUPON_ERROR:
      return {
        ...state,
        couponError: true
      }
    default:
      return state;
  }
}

export const updateCart = cart => {
  return {
    type: UPDATE_CART,
    payload: {
      cart
    }
  };
};

export const setCouponError = error => {
  return {
    type: SET_COUPON_ERROR,
    payload: {
      error
    },
  };
};

export const getCart = () => async dispatch => {
  try {
    dispatch({ type: GET_CART });

    const response = await fetch("/front-api/cart", {
      credentials: "include"
    });

    if (!response.ok) {
      throw new Error("problems");
    }
    const { Data } = await response.json();
    dispatch(updateCart(Data));

    return Data;
  } catch (error) {
    console.error(error);
  }
};

export const addItem = form => async dispatch => {
  try {
    dispatch({ type: ADD_ITEM });

    const formData = new FormData(form);

    const response = await fetch(`/front-api/cart/add`, {
      method: "POST",
      credentials: "same-origin",
      body: formData
    });

    if (!response.ok) {
      throw new Error("problems");
    }

    const { Data, msg } = await response.json();
    dispatch(updateCart(Data));
  } catch (error) {
    throw error;
  }
};

export const deleteItem = productId => async dispatch => {
  try {
    dispatch({ type: DELETE_ITEM });
    const response = await fetch(`/front-api/cart/delete/${productId}`, {
      credentials: "include",
    });

    if (!response.ok) {
      throw new Error("problems");
    }

    const { Data } = await response.json();
    dispatch(updateCart(Data));
  } catch (error) {
    console.error(error);
  }
};

export const updateItem = (cartItem, quantity) => async dispatch => {
  try {
    dispatch({ type: UPDATE_ITEM });
    const formData = new FormData();
    formData.set("cart_item", cartItem);
    formData.set("quantity", quantity);

    const response = await fetch(`/front-api/cart/update`, {
      method: "post",
      body: formData,
      credentials: "include"
    });

    if (!response.ok) {
      throw new Error("problems");
    }

    const { Data } = await response.json();
    dispatch(updateCart(Data));
  } catch (error) {
    console.error(error);
  }
};

export const addCoupon = form => async dispatch => {
  try {
    dispatch({ type: ADD_COUPON });

    const formData = new FormData(form);

    const response = await fetch(`/order/coupon`, {
      method: "POST",
      credentials: "include",
      body: formData
    });

    if (!response.ok) {
      throw new Error("problems");
    }

    const { Data } = await response;
    dispatch(updateCart(Data));
  } catch (error) {
    dispatch(setCouponError(error));
    throw error;
  }
};
