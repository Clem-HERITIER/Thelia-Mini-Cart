
import React, { Component } from "react";
import './Coupon.css';
import Loader from "../Loader/Loader";




class Coupon extends Component {
    state = {
        loading: true,
        showResult: false,
    }

    async checkCoupon(e) {
        this.setState({
            showResult: true,
            loading: true

        });
        await this.props.handleCoupon(e);
        this.setState({
            loading: false
        });
    }



    render() {
        const { loading, showResult } = this.state;
        const { hasError } = this.props;

        return (
            <form method='post' onSubmit={(e) => {
                e.preventDefault();
                this.checkCoupon(e.target);
            }}>
                <input type="hidden" name="thelia_coupon_code[_token]" value="HZK8GetVnMVUvLhUm-reY6R9ZuMosPIseGg8mAz_6sI" />
                <input type="hidden" name="thelia_coupon_code[success_url]" value="http://starter-thelia.lab/cart" />
                <input type="hidden" name="thelia_coupon_code[error_url]" value="http://starter-thelia.lab/cart" />
                <div className="row privilegeCode ">
                    <div className="col-5 text-uppercase">
                        Code Privilège
                        </div>
                    <div className="col-7 couponForm">
                        <label className="control-label sr-only" >Code :</label>
                        <input id="coupon" className="form-control" type="text" name="thelia_coupon_code[coupon-code]" defaultValue="" placeholder="Code promo" required="" />
                        <button className="Button" >Ok</button>
                        <div className='resultCoupon'>
                            {showResult ?
                                <>
                                    {hasError || !loading ?
                                        <>
                                            <p>{  }</p>
                                        </> : <Loader />
                                    }
                                </> : null
                            }
                        </div>
                    </div>
                </div>

            </form>
        );
    }
}

export default Coupon;


