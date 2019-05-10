import $ from "jquery";
import React, { Component } from "react";
import { Provider, connect } from "react-redux";

import ReactDOM from "react-dom";
import Header from "./Header/Header";
import CartItems from "./CartItems/CartItems";
import Total from "./Total/Total";
import Coupon from "./Coupon/Coupon";
import Loader from "./Loader/Loader"

import { getCart, deleteItem, updateItem, addCoupon } from "@js/redux/modules/cart";

class App extends Component {
  state = {
    loading: true
  };
  async componentDidMount() {
    await this.props.getCart();
    this.setState({
      loading: false
    })
  }
  render() {

    const { cart } = this.props;
    const { loading } = this.state;
    const { couponError } = cart;

    return (
      <div>
        {!loading ?
          <>
            <Header />
            {Array.isArray(cart.DataCartItems) ?
              <>
                <CartItems
                  data={cart.DataCartItems}
                  handleDelete={this.props.deleteItem}
                  handleUpdate={this.props.updateItem}
                />
                <Total data={cart.DataCart} />
                <Coupon handleCoupon={this.props.addCoupon} hasError={couponError} />
                <a href="/order/delivery"> <button className='Button'>Procéder au paiments</button></a>
              </>
              : <div className='emptyCart'>{cart.DataCartItems}</div>}
          </>
          : <Loader />}
      </div>
    );
  }
}

const ConnectedApp = connect(
  state => ({
    cart: state.cart
  }),
  dispatch => ({
    getCart: () => dispatch(getCart()),
    addCoupon: form => dispatch(addCoupon()),
    deleteItem: itemId => dispatch(deleteItem(itemId)),
    updateItem: (productId, quantity) =>
      dispatch(updateItem(productId, quantity))
  })
)(App);

export default () => {
  ReactDOM.render(
    <Provider store={store}>
      <ConnectedApp />
    </Provider>,
    document.getElementById("Mini-cart")
  );

  $(document).on("click", "[data-toggle-cart]", function (e) {
    e.preventDefault();
    const miniCart = $(".MiniCart");

    $(".MiniCart").toggleClass("active");
  });
  $(document).on("click", "[data-close-cart]", function (e) {
    e.preventDefault();
    $(".MiniCart").removeClass("active");
  });
};
