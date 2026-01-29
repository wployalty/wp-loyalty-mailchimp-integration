import React from 'react';
import Icon from "./Icon";
import {UiLabelContext} from "../../Context";

const BackContainer = ({click}) => {
    const labels = React.useContext(UiLabelContext)
    return <div className={`flex cursor-pointer items-center px-3 py-2 w-max justify-start `}
                onClick={click}
    >
        <div className={`flex gap-x-2  px-4 py-2 rounded-md border border-card_border`}>
            <Icon icon={"back"} color={"#161f31"}/>
            <p>{labels.common.back}</p>
        </div>
    </div>
};

export default BackContainer;