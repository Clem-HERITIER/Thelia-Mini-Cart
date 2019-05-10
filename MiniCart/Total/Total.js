import React from 'react';
import './Total.css';






function Total({ data = {} }) {

    return (
        <div>
            <div className="info" >
                <h3>Sous-Total</h3>
                <p>{data.TotalTaxedPrice}</p>
            </div>
            <div className="info" >
                <h3>Livraison</h3>
                <p>{data.Delivery ? data.Delivery.PostageFormatted : null}</p>
            </div>
            <div className="info" >
                <h3>Total</h3>
                <p>{data.TotalIncludeDelivery}</p>
            </div>
        </div>
    );
}


export default Total;
